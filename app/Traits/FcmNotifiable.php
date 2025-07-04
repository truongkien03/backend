<?php

namespace App\Traits;

use App\Models\Driver;
use App\Models\User;

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
            // Driver sử dụng topic-based notification
            return 'driver-' . $this->id;
        }
        
        if (get_class($this) == User::class) {
            // User sử dụng direct token notification
            $tokens = $this->fcm_token ?? [];
            
            // Đảm bảo trả về array
            if (!is_array($tokens)) {
                return $tokens ? [$tokens] : [];
            }
            
            return $tokens;
        }
        
        return [];
    }
}
