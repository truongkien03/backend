<?php

declare(strict_types=1);

return [
    /*
     * ------------------------------------------------------------------------
     * Default Firebase project
     * ------------------------------------------------------------------------
     */
    'default' => env('FIREBASE_PROJECT', 'app'),

    /*
     * ------------------------------------------------------------------------
     * Firebase project configurations
     * ------------------------------------------------------------------------
     */
    'projects' => [
        'app' => [
            /*
             * ------------------------------------------------------------------------
             * Credentials / Service Account (FCM HTTP v1 API)
             * ------------------------------------------------------------------------
             */
            'credentials' => [
                'file' => base_path(env('FIREBASE_CREDENTIALS', 'storage/firebase-service-account.json')),
                'auto_discovery' => true,
            ],

            /*
             * ------------------------------------------------------------------------
             * Project Configuration
             * ------------------------------------------------------------------------
             */
            'project_id' => env('FIREBASE_PROJECT_ID', 'delivery-app-datn'),

            /*
             * ------------------------------------------------------------------------
             * FCM HTTP v1 API Configuration
             * ------------------------------------------------------------------------
             */
            'fcm' => [
                'v1_url' => 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send',
                'timeout' => env('FIREBASE_FCM_TIMEOUT', 30),
                'scopes' => [
                    'https://www.googleapis.com/auth/firebase.messaging'
                ],
            ],

            /*
             * ------------------------------------------------------------------------
             * Notification Channels
             * ------------------------------------------------------------------------
             */
            'channels' => [
                'user_notifications' => [
                    'name' => 'User Notifications',
                    'description' => 'Thông báo cho app người dùng',
                    'sound' => 'default',
                    'vibration' => true,
                ],
                'driver_notifications' => [
                    'name' => 'Driver Notifications', 
                    'description' => 'Thông báo cho app tài xế',
                    'sound' => 'driver_alert.wav',
                    'vibration' => true,
                ],
            ],

            /*
             * ------------------------------------------------------------------------
             * Topic Configuration
             * ------------------------------------------------------------------------
             */
            'topics' => [
                'all_users' => 'all-users',
                'all_drivers' => 'all-drivers',
                'driver_prefix' => 'driver-', // driver-{id}
                'user_prefix' => 'user-',     // user-{id} (nếu cần)
            ],

            /*
             * ------------------------------------------------------------------------
             * Firebase Auth Component
             * ------------------------------------------------------------------------
             */

            'auth' => [
                'tenant_id' => env('FIREBASE_AUTH_TENANT_ID', null),
            ],

            /*
             * ------------------------------------------------------------------------
             * Firebase Realtime Database
             * ------------------------------------------------------------------------
             */

            'database' => [
                'url' => env('FIREBASE_DATABASE_URL', null),
            ],

            'dynamic_links' => [
                'default_domain' => env('FIREBASE_DYNAMIC_LINKS_DEFAULT_DOMAIN', null),
            ],

            /*
             * ------------------------------------------------------------------------
             * Firebase Cloud Storage
             * ------------------------------------------------------------------------
             */

            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_DEFAULT_BUCKET', null),
            ],

            /*
             * ------------------------------------------------------------------------
             * Caching
             * ------------------------------------------------------------------------
             */

            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),

            /*
             * ------------------------------------------------------------------------
             * Logging
             * ------------------------------------------------------------------------
             */

            'logging' => [
                'http_log_channel' => env('FIREBASE_HTTP_LOG_CHANNEL', null),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL', null),
            ],

            /*
             * ------------------------------------------------------------------------
             * HTTP Client Options
             * ------------------------------------------------------------------------
             */
            'http_client_options' => [
                'proxy' => env('FIREBASE_HTTP_CLIENT_PROXY', null),
                'timeout' => env('FIREBASE_HTTP_CLIENT_TIMEOUT', 30),
            ],

            /*
             * ------------------------------------------------------------------------
             * Debug (deprecated)
             * ------------------------------------------------------------------------
             */
            'debug' => env('FIREBASE_ENABLE_DEBUG', false),
        ],
    ],
];
