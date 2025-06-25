<?php

namespace App\Notifications;

use App\Fcm\FcmTopic;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;
use NotificationChannels\Fcm\FcmChannel;

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
        return ['broadcast', FcmTopic::class];
    }

    public function toFcm($notifiable)
    {
        $data = $this->toArray($notifiable);

        $notification = MessagingNotification::create('Default Title')
            ->withTitle('Thông báo hệ thống')
            ->withBody('Có đơn hàng mới');

        return [
            'data' => $data,
            'notification' => $notification
        ];
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
