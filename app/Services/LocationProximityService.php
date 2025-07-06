<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\Driver;
use App\Services\FcmV1Service;
use App\Jobs\FcmNotificationJob;
use App\Notifications\OrderProximityAlert;

class LocationProximityService
{
    private $fcmService;
    private $proximityRadius; // Bán kính gần (km)

    public function __construct()
    {
        $this->fcmService = app(FcmV1Service::class);
        $this->proximityRadius = config('const.proximity_radius', 2.0); // Mặc định 2km
    }

    /**
     * Xử lý khi có cập nhật tọa độ driver
     */
    public function processDriverLocationUpdate($driverId, $locationData)
    {
        try {
            Log::info("Processing proximity check for driver: {$driverId}", [
                'latitude' => $locationData['latitude'],
                'longitude' => $locationData['longitude']
            ]);

            // Kiểm tra driver có online không
            if (!($locationData['isOnline'] ?? false)) {
                Log::info("Driver {$driverId} is offline, skipping proximity check");
                return;
            }

            // Tìm các đơn hàng gần driver
            $nearbyOrders = $this->findNearbyOrders(
                $locationData['latitude'],
                $locationData['longitude']
            );

            if ($nearbyOrders->isEmpty()) {
                Log::info("No nearby orders found for driver: {$driverId}");
                return;
            }

            Log::info("Found " . $nearbyOrders->count() . " nearby orders for driver: {$driverId}");

            // Gửi thông báo cho từng đơn hàng gần
            foreach ($nearbyOrders as $order) {
                $this->sendProximityNotification($driverId, $order, $locationData);
            }

        } catch (\Exception $e) {
            Log::error("Error processing proximity check for driver: {$driverId}", [
                'error' => $e->getMessage(),
                'location_data' => $locationData
            ]);
        }
    }

    /**
     * Tìm các đơn hàng gần tọa độ cho trước
     */
    public function findNearbyOrders($latitude, $longitude)
    {
        try {
            // Tìm đơn hàng chưa có driver và trong bán kính gần
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
                ->having('distance', '<=', $this->proximityRadius)
                ->orderBy('distance')
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info("Found " . $orders->count() . " orders within {$this->proximityRadius}km radius");

            return $orders;

        } catch (\Exception $e) {
            Log::error("Error finding nearby orders", [
                'error' => $e->getMessage(),
                'latitude' => $latitude,
                'longitude' => $longitude
            ]);
            return collect();
        }
    }

    /**
     * Gửi thông báo FCM cho driver về đơn hàng gần
     */
    private function sendProximityNotification($driverId, $order, $driverLocation)
    {
        try {
            $driver = Driver::where('driver_id', $driverId)->first();
            if (!$driver) {
                Log::warning("Driver not found: {$driverId}");
                return;
            }

            // Tính khoảng cách
            $distance = $this->calculateDistance(
                $driverLocation['latitude'],
                $driverLocation['longitude'],
                $order->from_address['lat'],
                $order->from_address['lon']
            );

            // Chuẩn bị dữ liệu thông báo
            $notificationData = [
                'type' => 'order_proximity_alert',
                'order_id' => $order->id,
                'distance' => round($distance, 2),
                'from_address' => $order->from_address['desc'],
                'to_address' => $order->to_address['desc'],
                'shipping_cost' => $order->shipping_cost,
                'items_count' => count($order->items),
                'timestamp' => now()->toISOString(),
                'screen' => 'order_detail',
                'order_data' => [
                    'id' => $order->id,
                    'from_address' => $order->from_address,
                    'to_address' => $order->to_address,
                    'items' => $order->items,
                    'shipping_cost' => $order->shipping_cost,
                    'distance' => $order->distance,
                    'receiver' => $order->receiver
                ]
            ];

            // Gửi thông báo FCM
            $this->sendFcmNotification($driver, $notificationData, $distance);

            // Gửi notification qua Laravel Notification system
            $driver->notify(new OrderProximityAlert($order, $driver, $distance));

            Log::info("Sent proximity notification to driver: {$driverId}", [
                'order_id' => $order->id,
                'distance' => $distance
            ]);

        } catch (\Exception $e) {
            Log::error("Error sending proximity notification", [
                'driver_id' => $driverId,
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi thông báo FCM
     */
    private function sendFcmNotification($driver, $data, $distance)
    {
        try {
            $title = "Đơn hàng gần bạn!";
            $body = "Có đơn hàng cách bạn " . number_format($distance, 1) . "km. Nhận ngay!";

            // Gửi thông báo trực tiếp đến driver
            if ($driver->fcm_token) {
                $this->fcmService->sendToDevice(
                    $driver->fcm_token,
                    $title,
                    $body,
                    $data
                );
            }

            // Hoặc gửi qua topic nếu cần
            $this->fcmService->sendToTopic(
                config('firebase.projects.app.topics.all_drivers'),
                $title,
                $body,
                array_merge($data, ['driver_id' => $driver->driver_id])
            );

        } catch (\Exception $e) {
            Log::error("Error sending FCM notification", [
                'driver_id' => $driver->driver_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tính khoảng cách giữa 2 điểm (Haversine formula)
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Bán kính trái đất (km)

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Kiểm tra xem driver có đủ điều kiện nhận đơn hàng không
     */
    private function isDriverEligible($driver, $order)
    {
        // Kiểm tra driver có online không
        if (!$driver->is_online) {
            return false;
        }

        // Kiểm tra driver có trong danh sách bị loại trừ không
        if ($order->except_drivers && in_array($driver->id, $order->except_drivers)) {
            return false;
        }

        // Kiểm tra trạng thái driver
        if ($driver->status !== config('const.driver.status.free', 1)) {
            return false;
        }

        return true;
    }

    /**
     * Cập nhật bán kính gần
     */
    public function setProximityRadius($radius)
    {
        $this->proximityRadius = $radius;
    }

    /**
     * Lấy bán kính hiện tại
     */
    public function getProximityRadius()
    {
        return $this->proximityRadius;
    }
} 