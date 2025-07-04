<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use App\Fcm\FcmDirect;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\Notification as MessagingNotification;

class NoAvailableDriver extends Notification
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
            ->withTitle('Không tìm được tài xế')
            ->withBody('Đơn hàng đã bị huỷ do không tìm được tài xế');

        return [
            'data' => $data,
            'notification' => $notification
        ];
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
