<?php

namespace App\Notifications;

use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderHasBeenComplete extends Notification implements ShouldQueue
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
            'type' => 'order_completed',
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
            'Đơn hàng đã hoàn thành',
            'Đơn hàng của bạn đã được giao thành công. Cảm ơn bạn đã sử dụng dịch vụ!',
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
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
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
            'key' => "CompleteOder",
            'link' => "customer://Notification",
            'oderId' => (string) $this->order->id,
        ];
    }
}
