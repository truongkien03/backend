<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Driver;
use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderProximityAlert extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $order;
    public $driver;
    public $distance;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Order $order, Driver $driver, $distance)
    {
        $this->order = $order;
        $this->driver = $driver;
        $this->distance = $distance;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['broadcast', 'fcm_v1'];
    }

    /**
     * Send notification using FCM v1 API
     */
    public function toFcmV1($notifiable)
    {
        $fcmService = app(FcmV1Service::class);
        
        $title = "Đơn hàng gần bạn!";
        $body = "Có đơn hàng cách bạn " . number_format($this->distance, 1) . "km. Nhận ngay!";

        $data = [
            'type' => 'order_proximity_alert',
            'order_id' => $this->order->id,
            'distance' => round($this->distance, 2),
            'from_address' => $this->order->from_address['desc'],
            'to_address' => $this->order->to_address['desc'],
            'shipping_cost' => (string) $this->order->shipping_cost,
            'items_count' => (string) count($this->order->items),
            'timestamp' => now()->toISOString(),
            'screen' => 'order_detail',
            'order_data' => json_encode([
                'id' => $this->order->id,
                'from_address' => $this->order->from_address,
                'to_address' => $this->order->to_address,
                'items' => $this->order->items,
                'shipping_cost' => $this->order->shipping_cost,
                'distance' => $this->order->distance,
                'receiver' => $this->order->receiver
            ])
        ];

        // Gửi thông báo trực tiếp đến driver
        if ($this->driver->fcm_token) {
            return $fcmService->sendToDevice(
                $this->driver->fcm_token,
                $title,
                $body,
                $data
            );
        }

        // Fallback: gửi qua topic
        return $fcmService->sendToTopic(
            config('firebase.projects.app.topics.all_drivers'),
            $title,
            $body,
            array_merge($data, ['driver_id' => $this->driver->driver_id])
        );
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'type' => 'order_proximity_alert',
            'order_id' => $this->order->id,
            'driver_id' => $this->driver->driver_id,
            'distance' => $this->distance,
            'from_address' => $this->order->from_address['desc'],
            'to_address' => $this->order->to_address['desc'],
            'shipping_cost' => $this->order->shipping_cost,
            'items_count' => count($this->order->items),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get the broadcast event representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toBroadcast($notifiable)
    {
        return [
            'id' => $this->id,
            'type' => 'order_proximity_alert',
            'order_id' => $this->order->id,
            'driver_id' => $this->driver->driver_id,
            'distance' => $this->distance,
            'from_address' => $this->order->from_address['desc'],
            'to_address' => $this->order->to_address['desc'],
            'shipping_cost' => $this->order->shipping_cost,
            'items_count' => count($this->order->items),
            'timestamp' => now()->toISOString()
        ];
    }
} 