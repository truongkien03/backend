<?php

namespace App\Notifications;

use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverArrivedAtDestination extends Notification implements ShouldQueue
{
    use Queueable;

    public $order;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'fcm_v1'];
    }

    /**
     * Send notification using FCM v1 API
     */
    public function toFcmV1($notifiable)
    {
        $fcmService = app(FcmV1Service::class);
        
        $data = array_merge($this->toArray($notifiable), [
            'type' => 'driver_arrived_at_destination',
            'screen' => 'order_detail',
            'timestamp' => now()->toISOString(),
            'driver_name' => $this->order->driver->name ?? '',
            'driver_phone' => $this->order->driver->phone_number ?? '',
        ]);

        // Lấy FCM token của user
        $fcmTokens = $notifiable->fcm_token;
        
        if (empty($fcmTokens)) {
            return false;
        }

        // Nếu là array, gửi đến token đầu tiên
        if (is_array($fcmTokens)) {
            $token = $fcmTokens[0] ?? null;
        } else {
            $token = $fcmTokens;
        }

        if (!$token) {
            return false;
        }

        return $fcmService->sendToToken(
            $token,
            'Tài xế đã tới địa điểm giao hàng',
            'Tài xế đã tới địa điểm giao hàng. Vui lòng ra nhận hàng.',
            $data
        );
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('Tài xế đã tới địa điểm giao hàng.')
            ->line('Vui lòng ra nhận hàng.')
            ->action('Xem chi tiết đơn hàng', url('/orders/' . $this->order->id))
            ->line('Cảm ơn bạn đã sử dụng dịch vụ!');
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
            'key' => "DriverArrivedAtDestination",
            'link' => "customer://OrderDetail",
            'oderId' => (string) $this->order->id,
        ];
    }
} 