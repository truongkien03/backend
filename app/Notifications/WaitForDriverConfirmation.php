<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WaitForDriverConfirmation extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public $order;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Order $order)
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
        return ['broadcast', 'fcm_v1'];
    }

    /**
     * Send notification using FCM v1 API to all drivers
     */
    public function toFcmV1($notifiable)
    {
        $fcmService = app(FcmV1Service::class);
        
        $data = array_merge($this->toArray($notifiable), [
            'type' => 'new_order_available',
            'screen' => 'order_list',
            'timestamp' => now()->toISOString(),
            'from_address' => json_encode($this->order->from_address),
            'to_address' => json_encode($this->order->to_address),
            'distance' => (string) $this->order->distance,
            'shipping_cost' => (string) $this->order->shipping_cost,
        ]);

        // Gửi đến topic của tất cả drivers
        return $fcmService->sendToTopic(
            config('firebase.projects.app.topics.all_drivers'),
            'Đơn hàng mới cần giao!',
            'Có đơn hàng mới trong khu vực của bạn. Khoảng cách: ' . number_format($this->order->distance, 1) . 'km',
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
            'key' => "NewOder",
            'link' => "driver://AwaitAcceptOder",
            'oderId' => (string) $this->order->id,
        ];
    }
}
