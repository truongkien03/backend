<?php

namespace App\Services;

use Google\Client as GoogleClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class FcmV1Service
{
    protected $projectId;
    protected $credentialsPath;
    protected $fcmUrl;
    
    public function __construct()
    {
        $this->projectId = config('firebase.projects.app.project_id');
        $this->credentialsPath = config('firebase.projects.app.credentials.file');
        $this->fcmUrl = str_replace(
            '{project_id}', 
            $this->projectId, 
            config('firebase.projects.app.fcm.v1_url')
        );
    }
    
    /**
     * Lấy Access Token từ Google Service Account
     */
    private function getAccessToken()
    {
        return Cache::remember('fcm_access_token', 3300, function () { // Cache 55 phút
            try {
                $client = new GoogleClient();
                $client->setAuthConfig($this->credentialsPath);
                $client->addScope(config('firebase.projects.app.fcm.scopes'));
                
                $accessToken = $client->fetchAccessTokenWithAssertion();
                
                if (isset($accessToken['access_token'])) {
                    return $accessToken['access_token'];
                }
                
                throw new \Exception('Unable to get access token');
            } catch (\Exception $e) {
                Log::error('FCM Access Token Error: ' . $e->getMessage());
                throw $e;
            }
        });
    }
    
    /**
     * Gửi notification đến FCM token cụ thể (cho User)
     */
    public function sendToToken($token, $title, $body, $data = [])
    {
        $message = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge($data, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'app_type' => 'user'
                ]),
                'android' => [
                    'notification' => [
                        'channel_id' => config('firebase.projects.app.channels.user_notifications.name'),
                        'priority' => 'high',
                        'sound' => config('firebase.projects.app.channels.user_notifications.sound'),
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                    'fcm_options' => [
                        'analytics_label' => 'user_notification'
                    ],
                ],
            ]
        ];
        
        return $this->sendFcmRequest($message);
    }
    
    /**
     * Gửi notification đến topic (cho Driver)
     */
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        $message = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge($data, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'app_type' => 'driver'
                ]),
                'android' => [
                    'notification' => [
                        'channel_id' => config('firebase.projects.app.channels.driver_notifications.name'),
                        'priority' => 'high',
                        'sound' => config('firebase.projects.app.channels.driver_notifications.sound'),
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => config('firebase.projects.app.channels.driver_notifications.sound'),
                            'badge' => 1,
                        ],
                    ],
                    'fcm_options' => [
                        'analytics_label' => 'driver_notification'
                    ],
                ],
            ]
        ];
        
        return $this->sendFcmRequest($message);
    }
    
    /**
     * Gửi notification đến nhiều tokens (batch)
     */
    public function sendToMultipleTokens($tokens, $title, $body, $data = [])
    {
        if (empty($tokens)) {
            return false;
        }
        
        $results = [];
        
        // FCM v1 không hỗ trợ batch gửi như legacy API
        // Phải gửi từng token một hoặc dùng topic
        foreach ($tokens as $token) {
            $results[] = $this->sendToToken($token, $title, $body, $data);
        }
        
        return $results;
    }
    
    /**
     * Subscribe token vào topic (sử dụng IID API)
     */
    public function subscribeToTopic($token, $topic)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post('https://iid.googleapis.com/iid/v1/' . $token . '/rel/topics/' . $topic);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Subscribe Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unsubscribe token khỏi topic (sử dụng IID API)
     */
    public function unsubscribeFromTopic($token, $topic)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->delete('https://iid.googleapis.com/iid/v1/' . $token . '/rel/topics/' . $topic);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Unsubscribe Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gửi request đến FCM HTTP v1 API
     */
    private function sendFcmRequest($message)
    {
        try {
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30)
            ->post($this->fcmUrl, $message);
            
            $result = $response->json();
            
            if ($response->successful()) {
                Log::info('FCM Notification sent successfully', [
                    'response' => $result,
                    'message_id' => $result['name'] ?? null
                ]);
                return true;
            } else {
                Log::error('FCM Notification failed', [
                    'response' => $result,
                    'status' => $response->status(),
                    'message' => $message
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('FCM Request Error: ' . $e->getMessage(), [
                'message' => $message
            ]);
            return false;
        }
    }
    
    /**
     * Validate FCM token
     */
    public function validateToken($token)
    {
        try {
            // Gửi dry run để validate token
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => 'Test',
                        'body' => 'Validation test',
                    ],
                ],
                'validate_only' => true // Chỉ validate, không gửi thật
            ];
            
            $accessToken = $this->getAccessToken();
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $message);
            
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM Token Validation Error: ' . $e->getMessage());
            return false;
        }
    }
}
