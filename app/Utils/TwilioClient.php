<?php

namespace App\Utils;

use Twilio\Rest\Client;

class TwilioClient
{
    public static function getClient()
    {
        $account_sid = config('twilio.sid');
        $auth_token = config('twilio.token');

        return new Client($account_sid, $auth_token);
    }
}
