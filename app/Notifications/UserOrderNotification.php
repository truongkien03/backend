<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UserOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $order;
    protected $title;
    protected $body;
    protected $type;
    protected $additionalData;

    public function __construct(Order $order, $title, $body, $type = 'order_notification', $additionalData = [])
    {
        $this->order = $order;
        $this->title = $title;
        $this->body = $body;
        $this->type = $type;
        $this->additionalData = $additionalData;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['fcm_v1'];
    }

    /**
     * Send notification using FCM v1 API
     */
    public function toFcmV1($notifiable)
    {
        $fcmService = app(FcmV1Service::class);
        
        $data = array_merge([
            'order_id' => (string) $this->order->id,
            'type' => $this->type,
            'screen' => 'order_detail',
            'timestamp' => now()->toISOString(),
        ], $this->additionalData);

        // Lấy FCM token của user
        $fcmTokens = $notifiable->fcm_token;
        
        if (empty($fcmTokens)) {
            return false;
        }

        // Nếu là array, gửi đến token đầu tiên (hoặc có thể gửi đến tất cả)
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
            $this->title,
            $this->body,
            $data
        );
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable)
    {
        return [
            'order_id' => $this->order->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'data' => $this->additionalData,
        ];
    }
}
