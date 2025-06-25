<?php

namespace App\Fcm;

use App\Jobs\FcmNotificationJob;
use Kreait\Firebase\Messaging\CloudMessage;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\Exceptions\CouldNotSendNotification;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\Message;

class FcmTopic extends FcmChannel
{
    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     *
     * @return array
     * @throws \NotificationChannels\Fcm\Exceptions\CouldNotSendNotification
     * @throws \Kreait\Firebase\Exception\FirebaseException
     */
    public function send($notifiable, Notification $notification)
    {
        $topic = $notifiable->routeNotificationFor('fcm', $notification);

        if (empty($topic)) {
            return;
        }

        // Get the message from the notification class
        $data = $notification->toFcm($notifiable);

        // build message
        $fcmMessage = CloudMessage::withTarget('topic', $topic)
            ->withData($data['data'])
            ->withNotification($data['notification']);

        if (!$fcmMessage instanceof Message) {
            throw CouldNotSendNotification::invalidMessage();
        }

        try {
            dispatch(new FcmNotificationJob($fcmMessage));
        } catch (MessagingException $exception) {
            $this->failedNotification($notifiable, $notification, $exception);
            throw CouldNotSendNotification::serviceRespondedWithAnError($exception);
        }
    }
}
