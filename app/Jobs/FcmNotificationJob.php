<?php

namespace App\Jobs;

use App\Services\FcmV1Service;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FcmNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $token;
    public $title;
    public $body;
    public $data;
    public $type; // 'token' or 'topic'

    /**
     * Create a new job instance for FCM v1 API
     *
     * @param string $token FCM token or topic name
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Extra data payload
     * @param string $type 'token' or 'topic'
     */
    public function __construct($token, $title, $body, $data = [], $type = 'token')
    {
        $this->token = $token;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
        $this->type = $type;
    }

    /**
     * Execute the job using FCM v1 API
     */
    public function handle(FcmV1Service $fcmService)
    {
        try {
            if ($this->type === 'topic') {
                $result = $fcmService->sendToTopic($this->token, $this->title, $this->body, $this->data);
            } else {
                $result = $fcmService->sendToToken($this->token, $this->title, $this->body, $this->data);
            }

            if (!$result) {
                Log::warning('FCM notification failed to send', [
                    'type' => $this->type,
                    'target' => $this->type === 'token' ? substr($this->token, 0, 20) . '...' : $this->token,
                    'title' => $this->title
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('FCM notification job failed', [
                'type' => $this->type,
                'target' => $this->type === 'token' ? substr($this->token, 0, 20) . '...' : $this->token,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
