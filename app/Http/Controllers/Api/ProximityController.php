<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LocationProximityService;
use App\Models\Order;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProximityController extends Controller
{
    private $proximityService;

    public function __construct()
    {
        $this->proximityService = app(LocationProximityService::class);
    }

    /**
     * Tìm đơn hàng gần tọa độ cho trước
     */
    public function findNearbyOrders(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'radius' => 'nullable|numeric|min:0.1|max:50',
                'limit' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 422);
            }

            $latitude = $request->latitude;
            $longitude = $request->longitude;
            $radius = $request->radius ?? 2.0;
            $limit = $request->limit ?? 20;

            // Set custom radius
            $this->proximityService->setProximityRadius($radius);

            // Tìm đơn hàng gần
            $orders = Order::selectRaw("
                    *,
                    6371 * acos(
                        cos( radians(?) ) *
                        cos( radians( JSON_EXTRACT(from_address, '$.lat') ) ) *
                        cos( radians( JSON_EXTRACT(from_address, '$.lon') ) - radians(?) ) +
                        sin( radians(?) ) *
                        sin( radians( JSON_EXTRACT(from_address, '$.lat') ) )
                    ) as distance
                ", [$latitude, $longitude, $latitude])
                ->whereNull('driver_id')
                ->where('status_code', config('const.order.status.pending', 1))
                ->having('distance', '<=', $radius)
                ->orderBy('distance')
                ->orderBy('created_at', 'asc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'count' => $orders->count(),
                    'search_coordinates' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ],
                    'radius_km' => $radius,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error finding nearby orders', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Test proximity check cho driver cụ thể
     */
    public function testDriverProximity(Request $request, $driverId)
    {
        try {
            $driver = Driver::where('driver_id', $driverId)->first();
            if (!$driver) {
                return response()->json([
                    'error' => true,
                    'message' => 'Driver not found'
                ], 404);
            }

            if (!$driver->current_latitude || !$driver->current_longitude) {
                return response()->json([
                    'error' => true,
                    'message' => 'Driver has no current location'
                ], 400);
            }

            $locationData = [
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'isOnline' => $driver->is_online,
                'accuracy' => 10,
                'speed' => $driver->current_speed ?? 0,
                'bearing' => $driver->current_bearing ?? 0,
                'timestamp' => time() * 1000
            ];

            // Tìm đơn hàng gần
            $nearbyOrders = $this->proximityService->findNearbyOrders(
                $driver->current_latitude,
                $driver->current_longitude
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'driver' => [
                        'id' => $driver->id,
                        'driver_id' => $driver->driver_id,
                        'name' => $driver->name,
                        'current_location' => [
                            'latitude' => $driver->current_latitude,
                            'longitude' => $driver->current_longitude
                        ],
                        'is_online' => $driver->is_online,
                        'status' => $driver->status
                    ],
                    'nearby_orders' => $nearbyOrders,
                    'orders_count' => $nearbyOrders->count(),
                    'proximity_radius' => $this->proximityService->getProximityRadius(),
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error testing driver proximity', [
                'driver_id' => $driverId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Simulate location update và trigger proximity check
     */
    public function simulateLocationUpdate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'is_online' => 'nullable|boolean',
                'accuracy' => 'nullable|numeric|min:0',
                'speed' => 'nullable|numeric|min:0',
                'bearing' => 'nullable|numeric|between:0,360'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid parameters',
                    'errors' => $validator->errors()
                ], 422);
            }

            $locationData = $request->all();
            $locationData['timestamp'] = time() * 1000;

            // Dispatch job để xử lý proximity check
            dispatch(new \App\Jobs\ProcessProximityCheck($request->driver_id, $locationData));

            return response()->json([
                'success' => true,
                'message' => 'Location update simulated and proximity check queued',
                'data' => [
                    'driver_id' => $request->driver_id,
                    'location' => [
                        'latitude' => $request->latitude,
                        'longitude' => $request->longitude,
                        'is_online' => $request->is_online ?? true
                    ],
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error simulating location update', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Lấy thống kê proximity
     */
    public function getProximityStats()
    {
        try {
            $pendingOrders = Order::whereNull('driver_id')
                ->where('status_code', config('const.order.status.pending', 1))
                ->count();

            $onlineDrivers = Driver::where('is_online', true)->count();

            $totalDrivers = Driver::count();

            return response()->json([
                'success' => true,
                'data' => [
                    'pending_orders' => $pendingOrders,
                    'online_drivers' => $onlineDrivers,
                    'total_drivers' => $totalDrivers,
                    'proximity_radius' => $this->proximityService->getProximityRadius(),
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting proximity stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }
} 