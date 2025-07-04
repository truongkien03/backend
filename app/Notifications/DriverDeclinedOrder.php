<?php

namespace App\Notifications;

use App\Fcm\FcmDirect;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;

class DriverDeclinedOrder extends Notification
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
        return ['database', FcmDirect::class];
    }

    public function toFcm($notifiable)
    {
        $data = $this->toArray($notifiable);

        $notification = MessagingNotification::create('Default Title')
            ->withTitle('Đơn hàng đã bị từ chối')
            ->withBody('Đơn hàng đã bị từ chối')
            ->withImageUrl('http://lorempixel.com/200/400/');

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
