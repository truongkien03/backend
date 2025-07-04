<?php

namespace App\Fcm;

use App\Jobs\FcmNotificationJob;
use Kreait\Firebase\Messaging\CloudMessage;
use NotificationChannels\Fcm\FcmChannel;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\Exceptions\CouldNotSendNotification;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\Message;

class FcmDirect extends FcmChannel
{
    /**
     * Send notification directly to FCM tokens (for Users)
     *
     * @param mixed $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     *
     * @return array
     * @throws \NotificationChannels\Fcm\Exceptions\CouldNotSendNotification
     */
    public function send($notifiable, Notification $notification)
    {
        $tokens = $notifiable->routeNotificationFor('fcm', $notification);

        if (empty($tokens) || !is_array($tokens)) {
            return;
        }

        // Get the message from the notification class
        $data = $notification->toFcm($notifiable);

        foreach ($tokens as $token) {
            if (empty($token)) {
                continue;
            }

            try {
                // Build message for each token
                $fcmMessage = CloudMessage::withTarget('token', $token)
                    ->withData($data['data'])
                    ->withNotification($data['notification']);

                if (!$fcmMessage instanceof Message) {
                    throw CouldNotSendNotification::invalidMessage();
                }

                dispatch(new FcmNotificationJob($fcmMessage));
            } catch (MessagingException $exception) {
                $this->failedNotification($notifiable, $notification, $exception);
                // Continue with other tokens even if one fails
                continue;
            }
        }
    }
}
