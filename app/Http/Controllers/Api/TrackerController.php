<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tracker;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TrackerController extends Controller
{
    /**
     * Cập nhật thông tin tracker từ Flutter app (thay thế Firebase Function)
     */
    public function updateFromApp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'accuracy' => 'nullable|numeric|min:0',
                'bearing' => 'nullable|numeric|between:0,360',
                'speed' => 'nullable|numeric|min:0',
                'is_online' => 'nullable|boolean',
                'status' => 'nullable|integer',
                'timestamp' => 'nullable|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Invalid data format',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            
            // Dispatch job để xử lý async
            dispatch(new \App\Jobs\ProcessFirebaseLocationUpdate($data['driver_id'], $data));

            return response()->json([
                'success' => true,
                'message' => 'Location update queued successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error queuing location update', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin tracker từ Firebase Function
     */
    public function updateFromFirebase(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'driver_id' => 'required|string',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'accuracy' => 'nullable|numeric|min:0',
                'bearing' => 'nullable|numeric|between:0,360',
                'speed' => 'nullable|numeric|min:0',
                'is_online' => 'nullable|boolean',
                'status' => 'nullable|integer',
                'timestamp' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                Log::warning('Invalid tracker data from Firebase', [
                    'errors' => $validator->errors(),
                    'data' => $request->all()
                ]);

                return response()->json([
                    'error' => true,
                    'message' => 'Invalid data format',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->all();
            
            // Chuyển đổi timestamp nếu cần
            if (isset($data['timestamp']) && is_numeric($data['timestamp'])) {
                $data['timestamp'] = date('Y-m-d H:i:s', $data['timestamp'] / 1000);
            }

            // Tìm hoặc tạo tracker record
            $tracker = Tracker::updateOrCreate(
                ['driver_id' => $data['driver_id']],
                [
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'accuracy' => $data['accuracy'] ?? 0,
                    'bearing' => $data['bearing'] ?? 0,
                    'speed' => $data['speed'] ?? 0,
                    'is_online' => $data['is_online'] ?? false,
                    'status' => $data['status'] ?? 0,
                    'timestamp' => $data['timestamp'] ?? now(),
                    'updated_at' => now()
                ]
            );

            // Cập nhật thông tin driver nếu cần
            $driver = Driver::where('driver_id', $data['driver_id'])->first();
            if ($driver) {
                $driver->update([
                    'current_latitude' => $data['latitude'],
                    'current_longitude' => $data['longitude'],
                    'is_online' => $data['is_online'] ?? false,
                    'last_location_update' => now()
                ]);
            }

            Log::info('Tracker updated from Firebase', [
                'driver_id' => $data['driver_id'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'is_online' => $data['is_online'] ?? false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tracker updated successfully',
                'data' => $tracker
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating tracker from Firebase', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Lấy thông tin tracker của driver
     */
    public function getDriverTracker($driverId)
    {
        try {
            $tracker = Tracker::where('driver_id', $driverId)->first();
            
            if (!$tracker) {
                return response()->json([
                    'error' => true,
                    'message' => 'Tracker not found for this driver'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $tracker
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting driver tracker', [
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
     * Lấy tất cả tracker đang online
     */
    public function getOnlineTrackers()
    {
        try {
            $trackers = Tracker::where('is_online', true)
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $trackers,
                'count' => $trackers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting online trackers', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Lấy tất cả driver locations realtime
     */
    public function getAllDriverLocations()
    {
        try {
            $trackers = Tracker::where('is_online', true)
                ->select('driver_id', 'latitude', 'longitude', 'accuracy', 'bearing', 'speed', 'is_online', 'status', 'timestamp')
                ->orderBy('updated_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $trackers,
                'count' => $trackers->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting all driver locations', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Lấy lịch sử tracker của driver
     */
    public function getDriverHistory($driverId, Request $request)
    {
        try {
            $limit = $request->get('limit', 50);
            $trackers = Tracker::where('driver_id', $driverId)
                ->orderBy('timestamp', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $trackers,
                'count' => $trackers->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting driver history', [
                'driver_id' => $driverId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => true,
                'message' => 'Internal server error'
            ], 500);
        }
    }
} 