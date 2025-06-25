<?php

namespace App\Traits;

use App\Models\Driver;

trait FcmNotifiable
{
    /**
     * Specifies the user's FCM token
     *
     * @return string|array
     */
    public function routeNotificationForFcm()
    {
        if (get_class($this) == Driver::class) {
            return 'driver-' . $this->id;
        }
        return 'user' . $this->id;
    }
}
