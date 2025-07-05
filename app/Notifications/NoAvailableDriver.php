<?php

namespace App\Notifications;

use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NoAvailableDriver extends Notification implements ShouldQueue
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
            'type' => 'no_driver_available',
            'screen' => 'order_detail',
            'timestamp' => now()->toISOString(),
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
            'Không tìm được tài xế',
            'Xin lỗi! Hiện tại không có tài xế nào trong khu vực. Vui lòng thử lại sau.',
            $data
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
            'key' => "NoAvailableDriver",
            'link' => "customer://Notification",
            'oderId' => (string) $this->order->id,
        ];
    }
}
