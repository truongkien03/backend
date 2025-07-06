<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Appwrite Configuration
    |--------------------------------------------------------------------------
    |
    | Cấu hình kết nối Appwrite cho dự án Laravel
    |
    */

    // Appwrite Project ID
    'project_id' => env('APPWRITE_PROJECT_ID', ''),

    // Appwrite API Endpoint
    'endpoint' => env('APPWRITE_ENDPOINT', 'https://cloud.appwrite.io/v1'),

    // Appwrite API Key
    'api_key' => env('APPWRITE_API_KEY', ''),

    // Appwrite Database ID
    'database_id' => env('APPWRITE_DATABASE_ID', ''),

    // Appwrite Storage Bucket ID
    'storage_bucket_id' => env('APPWRITE_STORAGE_BUCKET_ID', ''),

    // Appwrite Collection IDs
    'collections' => [
        'users' => env('APPWRITE_COLLECTION_USERS', ''),
        'drivers' => env('APPWRITE_COLLECTION_DRIVERS', ''),
        'orders' => env('APPWRITE_COLLECTION_ORDERS', ''),
        'locations' => env('APPWRITE_COLLECTION_LOCATIONS', ''),
        'notifications' => env('APPWRITE_COLLECTION_NOTIFICATIONS', ''),
    ],

    // Appwrite Function IDs
    'functions' => [
        'process_location' => env('APPWRITE_FUNCTION_PROCESS_LOCATION', ''),
        'send_notification' => env('APPWRITE_FUNCTION_SEND_NOTIFICATION', ''),
    ],

    // Appwrite Realtime Configuration
    'realtime' => [
        'enabled' => env('APPWRITE_REALTIME_ENABLED', true),
        'channels' => [
            'locations' => 'locations',
            'orders' => 'orders',
            'drivers' => 'drivers',
        ],
    ],
]; 