<?php

namespace App\Notifications;

use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DriverDeclinedOrder extends Notification implements ShouldQueue
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
            'type' => 'order_declined',
            'screen' => 'order_detail',
            'timestamp' => now()->toISOString(),
        ]);

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
            'Đơn hàng đã bị từ chối',
            'Tài xế đã từ chối đơn hàng của bạn. Hệ thống sẽ tìm tài xế khác.',
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
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'));
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
            'key' => "CancelOder",
            'link' => "customer://Notification",
            'oderId' => (string) $this->order->id,
        ];
    }
}
