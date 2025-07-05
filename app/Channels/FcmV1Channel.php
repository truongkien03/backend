<?php

namespace App\Channels;

use App\Services\FcmV1Service;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FcmV1Channel
{
    protected $fcmService;

    public function __construct(FcmV1Service $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            if (method_exists($notification, 'toFcmV1')) {
                return $notification->toFcmV1($notifiable);
            }

            Log::warning('Notification does not have toFcmV1 method', [
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable)
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('FCM v1 Channel Error', [
                'error' => $e->getMessage(),
                'notification' => get_class($notification),
                'notifiable' => get_class($notifiable)
            ]);

            return false;
        }
    }
}
