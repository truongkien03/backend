<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FindRandomDriverForOrder;
use App\Models\Driver;
use App\Models\Order;
use App\Notifications\WaitForDriverConfirmation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * @OA\Post(
     *      path="/orders",
     *      operationId="createOrder",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Parameter(
     *          name="user_note",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string|text"
     *          )
     *      ),
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_address' => 'required|json',
            'to_address' => 'required|json',
            'items' => 'required|json',
            'discount' => 'nullable|numeric',
            'user_note' => 'nullable|max:1000',
            'receiver' => 'required|json',
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $request['user_id'] = auth()->id();

        $request['from_address'] = $origin = json_decode($request['from_address'], true);
        $request['to_address'] = $destiny = json_decode($request['to_address'], true);
        $request['items'] = json_decode($request['items'], true);
        $request['receiver'] = json_decode($request['receiver'], true);

        $distanceInKilometer = $this->getDistanceInKilometer(
            implode(',', array_intersect_key($origin, ['lat' => 0, 'lon' => 0])),
            implode(',', array_intersect_key($destiny, ['lat' => 0, 'lon' => 0]))
        );

        if ($distanceInKilometer > 100) {
            return response()->json([
                'error' => true,
                'message' => [
                    'to_address' => [
                        'Hệ thống tạm thời không hỗ trợ đơn hàng xa hơn 100km'
                    ]
                ]
            ], 422);
        }

        $request['distance'] = $distanceInKilometer;
        $request['shipping_cost'] = $this->calculateShippingFeeAmount($distanceInKilometer);

        $order = Order::create($request->only([
            'user_id',
            'from_address',
            'to_address',
            'items',
            'shipping_cost',
            'distance',
            'user_note',
            'receiver'
        ]));

        // Tự động tìm và gửi thông báo cho tài xế ngẫu nhiên
        dispatch(new FindRandomDriverForOrder($order));

        return response()->json([
            'data' => $order
        ]);
    }

    /**
     * @OA\Get(
     *      path="/orders/inproccess",
     *      operationId="getInProcessList",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function getInProcessList(Request $request)
    {
        return Order::where('user_id', auth()->id())
            ->where('status_code', config('const.order.status.inprocess'))
            ->with('driver')
            ->latest()
            ->paginate();
    }

    /**
     * @OA\Get(
     *      path="/orders/completed",
     *      operationId="getCompleteList",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function getCompleteList(Request $request)
    {
        return Order::where('user_id', auth()->id())
            ->where('status_code', config('const.order.status.completed'))
            ->with('driver')
            ->latest()
            ->paginate();
    }

    /**
     * @OA\Get(
     *      path="/orders/{orderId}",
     *      operationId="detailOrder",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function detail(Request $request, Order $order)
    {
        return $order->load('driver');
    }

    /**
     * @OA\Post(
     *      path="/shipping-fee",
     *      operationId="calculateShippingFee",
     *      tags={"Order"},
     *      summary="Calculate shipping fee based on pickup and delivery locations",
     *      description="Calculate shipping fee including distance calculation and peak hour surcharge",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function calculateShippingFee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_address' => 'required|json',
            'from_address.lat' => 'required|numeric|between:-90,90',
            'from_address.lon' => 'required|numeric|between:-180,180', 
            'from_address.desc' => 'required|string|max:255',
            'to_address' => 'required|json',
            'to_address.lat' => 'required|numeric|between:-90,90',
            'to_address.lon' => 'required|numeric|between:-180,180',
            'to_address.desc' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        // Parse JSON addresses
        $fromAddress = is_string($request['from_address']) 
            ? json_decode($request['from_address'], true) 
            : $request['from_address'];
            
        $toAddress = is_string($request['to_address']) 
            ? json_decode($request['to_address'], true) 
            : $request['to_address'];

        // Calculate distance using coordinates
        $distanceInKilometer = $this->getDistanceInKilometer(
            $fromAddress['lat'] . ',' . $fromAddress['lon'],
            $toAddress['lat'] . ',' . $toAddress['lon']
        );

        // Check distance limit
        if ($distanceInKilometer > 100) {
            return response()->json([
                'error' => true,
                'message' => [
                    'distance' => [
                        'Hệ thống tạm thời không hỗ trợ đơn hàng xa hơn 100km'
                    ]
                ]
            ], 422);
        }

        $shippingFee = $this->calculateShippingFeeAmount($distanceInKilometer);
        $estimatedTime = $this->calculateEstimatedTime($distanceInKilometer);

        return response()->json([
            'data' => [
                'distance' => round($distanceInKilometer, 2),
                'shipping_fee' => $shippingFee,
                'estimated_time' => $estimatedTime,
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'calculated_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * @OA\Get(
     *      path="/orders/{orderId}/drivers/recommended",
     *      operationId="getRecommendedDriver",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function getRecommendedDriver(Request $request, Order $order)
    {
        $place = $order->from_address;

        $lat2 = $place['lat'];
        $lng2 = $place['lon'];

        $drivers = Driver::has('profile')
            ->selectRaw(
                "*,
            6371 * acos(
                cos( radians($lat2) )
              * cos( radians( JSON_EXTRACT(current_location, '$.lat') ) )
              * cos( radians( JSON_EXTRACT(current_location, '$.lon') ) - radians($lng2) )
              + sin( radians($lat2) )
              * sin( radians( JSON_EXTRACT(current_location, '$.lat') ) )
                ) as distance"
            )
            ->where('status', config('const.driver.status.free'))
            ->orderBy('distance')
            ->orderBy('review_rate', 'desc')
            ->paginate();

        return $drivers;
    }

    private function getDistanceInKilometerAsCrowFly($fromAddress, $toAddress)
    {
        $fromAddress = explode(',', $fromAddress);
        $toAddress = explode(',', $toAddress);

        $latitude1 = $fromAddress[0];
        $longitude1 = $fromAddress[1];
        $latitude2 = $toAddress[0];
        $longitude2 = $toAddress[1];

        $theta = $longitude1 - $longitude2;
        $miles = (sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))) + (cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta)));
        $miles = acos($miles);
        $miles = rad2deg($miles);
        $miles = $miles * 60 * 1.1515;
        $kilometers = $miles * 1.609344;

        return $kilometers;
    }

    private function getDistanceInKilometer($fromAddress, $toAddress)
    {
        // OSRM API sử dụng format: longitude,latitude
        // $fromAddress và $toAddress format: "lat,lon"
        $fromCoords = explode(',', $fromAddress);
        $toCoords = explode(',', $toAddress);
        
        // Convert to OSRM format: lon,lat
        $fromOSRM = $fromCoords[1] . ',' . $fromCoords[0]; // lon,lat
        $toOSRM = $toCoords[1] . ',' . $toCoords[0];       // lon,lat
        
        try {
            // Sử dụng config OSM thay vì hardcode URL
            $baseUrl = config('osm.osrm.base_url', 'http://router.project-osrm.org');
            $timeout = config('osm.osrm.timeout', 10);
            
            $osrmUrl = "{$baseUrl}/route/v1/driving/{$fromOSRM};{$toOSRM}";
            
            $response = json_decode(Http::timeout($timeout)->get($osrmUrl, [
                'overview' => 'false',
                'steps' => 'false'
            ]), true);

            if (isset($response['code']) && $response['code'] === 'Ok' && !empty($response['routes'])) {
                $distanceInMeters = $response['routes'][0]['distance'];
                $distanceInKilometers = $distanceInMeters / 1000;
                
                return $distanceInKilometers;
            }
            
            // Fallback: Nếu OSRM không hoạt động, dùng khoảng cách đường chim bay
            return $this->getDistanceInKilometerAsCrowFly($fromAddress, $toAddress);
            
        } catch (\Exception $e) {
            // Log lỗi để debug
            \Log::warning('OSRM API failed: ' . $e->getMessage());
            
            // Fallback: Tính khoảng cách đường chim bay
            return $this->getDistanceInKilometerAsCrowFly($fromAddress, $toAddress);
        }
    }

    /**
     * @OA\Post(
     *      path="/orders/{orderId}/drivers",
     *      operationId="updateDriver",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function updateDriver(Request $request, Order $order)
    {
        if (!in_array(
            $order->status_code,
            [
                config('const.order.status.pending'),
                config('const.order.status.cancled_by_driver')
            ]
        )) {
            return response()->json([
                'error' => true,
                'message' => ['Đơn hàng không thể cập nhật lại tài xế']
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'driver_id' => ['bail', 'required', 'exists:drivers,id', function ($attr, $val, $fail) {
                if (Driver::find($val)->status != config('const.driver.status.free')) {
                    $fail('Tài xế hiện đang không sẵn sàng');
                }
                if (!Driver::find($val)->profile) {
                    $fail('Tài xế hiện đang không sẵn sàng');
                }
            }]
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $order->update([
            'driver_id' => $request['driver_id'],
        ]);

        Driver::find($request['driver_id'])->notify(new WaitForDriverConfirmation($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    public function updateRandomDriver(Request $request, Order $order)
    {
        if (!in_array(
            $order->status_code,
            [
                config('const.order.status.pending'),
                config('const.order.status.cancled_by_driver')
            ]
        )) {
            return response()->json([
                'error' => true,
                'message' => ['Đơn hàng không thể cập nhật lại tài xế']
            ], 422);
        }

        $order->update([
            'is_sharable' => true,
        ]);

        dispatch(new FindRandomDriverForOrder($order));

        return response()->json([
            'data' => $order->refresh()
        ]);
    }

    /**
     * @OA\Post(
     *      path="/orders/{orderId}/review",
     *      operationId="reviewDriver",
     *      tags={"Order"},
     *      summary="",
     *      description="",
     *      @OA\Response(response=200,description="successful operation", @OA\JsonContent()),
     *      @OA\Response(response=422, description="Bad request"),
     *      @OA\Response(response=500, description="Server error"),
     *      security={
     *          {"bearerAuth": {}}
     *      }
     *     )
     */
    public function reviewDriver(Request $request, Order $order)
    {
        $validator = Validator::make($request->all(), [
            'driver_rate' => 'required|numeric|min:1|max:5'
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $order->update([
            'driver_rate' => $request['driver_rate']
        ]);

        $driver = Driver::find($order->driver_id);

        $driver->update([
            'review_rate' => Order::where('driver_id', $order->driver_id)->avg('driver_rate'),
        ]);

        return response()->json([
            'data' => $driver->refresh()
        ]);
    }

    private function calculateShippingFeeAmount($distanceInKilometer)
    {
        $shippingFee = config('const.cost.first_km');
        if (($distanceInKilometer - 1) > 0) {
            $shippingFee += config('const.cost.from_2nd_km') * ($distanceInKilometer - 1);
        }

        if (in_array(date('H'), config('const.cost.peak_hours'))) {
            $shippingFee += config('const.cost.peak_hour_addition_rate') * $shippingFee;
        }

        return $shippingFee;
    }

    private function calculateEstimatedTime($distanceInKilometer)
    {
        // Tốc độ trung bình 30km/h trong thành phố
        $averageSpeedKmh = 30;
        $timeInHours = $distanceInKilometer / $averageSpeedKmh;
        $timeInMinutes = round($timeInHours * 60);
        
        // Thêm buffer time cho việc lấy hàng và giao hàng
        $bufferMinutes = 10;
        $totalMinutes = $timeInMinutes + $bufferMinutes;
        
        if ($totalMinutes <= 15) {
            return "10-15 phút";
        } elseif ($totalMinutes <= 30) {
            return "15-30 phút";
        } elseif ($totalMinutes <= 45) {
            return "30-45 phút";
        } elseif ($totalMinutes <= 60) {
            return "45-60 phút";
        } else {
            $hours = ceil($totalMinutes / 60);
            return $hours . " giờ";
        }
    }

    /**
     * Helper method để lấy route từ OSRM cho Flutter app
     * Trả về cả khoảng cách và geometry để vẽ route trên map
     */
    private function getOSRMRoute($fromLat, $fromLon, $toLat, $toLon)
    {
        try {
            $baseUrl = config('osm.osrm.base_url', 'http://router.project-osrm.org');
            $timeout = config('osm.osrm.timeout', 10);
            
            $osrmUrl = "{$baseUrl}/route/v1/driving/{$fromLon},{$fromLat};{$toLon},{$toLat}";
            
            $response = json_decode(Http::timeout($timeout)->get($osrmUrl, [
                'overview' => 'full',
                'geometries' => 'geojson'
            ]), true);

            if (isset($response['code']) && $response['code'] === 'Ok' && !empty($response['routes'])) {
                $route = $response['routes'][0];
                
                return [
                    'distance' => $route['distance'] / 1000, // km
                    'duration' => $route['duration'] / 60,   // minutes
                    'geometry' => $route['geometry']['coordinates'] ?? null
                ];
            }
            
            return null;
            
        } catch (\Exception $e) {
            \Log::warning('OSRM Route API failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * API endpoint để Flutter app lấy route với geometry
     * GET /api/route?from_lat=10.762622&from_lon=106.660172&to_lat=10.772622&to_lon=106.670172
     */
    public function getRoute(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_lat' => 'required|numeric',
            'from_lon' => 'required|numeric',
            'to_lat' => 'required|numeric',
            'to_lon' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response([
                'error' => true,
                'message' => $validator->messages()
            ], 422);
        }

        $route = $this->getOSRMRoute(
            $request['from_lat'],
            $request['from_lon'],
            $request['to_lat'], 
            $request['to_lon']
        );

        if ($route) {
            return response()->json([
                'success' => true,
                'data' => $route
            ]);
        }

        return response()->json([
            'error' => true,
            'message' => 'Cannot calculate route'
        ], 500);
    }
}
