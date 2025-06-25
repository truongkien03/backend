<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $fcmMessage;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($fcmMessage)
    {
        $this->fcmMessage = $fcmMessage;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $messaging = app('firebase.messaging');

        return $messaging->send($this->fcmMessage);
    }
}
