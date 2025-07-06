<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AppwriteRealtimeService;
use App\Services\AppwriteStorageService;
use App\Services\AppwriteFunctionsService;
use Illuminate\Support\Facades\Log;

class AppwriteController extends Controller
{
    private $realtimeService;
    private $storageService;
    private $functionsService;

    public function __construct()
    {
        $this->realtimeService = new AppwriteRealtimeService();
        $this->storageService = new AppwriteStorageService();
        $this->functionsService = new AppwriteFunctionsService();
    }

    /**
     * Test kết nối Appwrite
     */
    public function testConnection(): JsonResponse
    {
        try {
            $allData = $this->realtimeService->getAllData();
            
            return response()->json([
                'success' => true,
                'message' => 'Appwrite connection successful',
                'data' => $allData
            ]);

        } catch (\Exception $e) {
            Log::error('Appwrite connection test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Appwrite connection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lưu location mới
     */
    public function saveLocation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'driver_id' => 'required|string',
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
                'speed' => 'nullable|numeric',
                'bearing' => 'nullable|numeric',
                'accuracy' => 'nullable|numeric',
                'isOnline' => 'boolean',
                'status' => 'string'
            ]);

            $locationData = [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'speed' => $request->speed ?? 0,
                'bearing' => $request->bearing ?? 0,
                'accuracy' => $request->accuracy ?? 0,
                'isOnline' => $request->isOnline ?? true,
                'status' => $request->status ?? 'active',
                'timestamp' => time() * 1000
            ];

            $documentId = $this->realtimeService->saveLocation($request->driver_id, $locationData);

            if ($documentId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Location saved successfully',
                    'document_id' => $documentId
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save location'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to save location: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy location của driver
     */
    public function getDriverLocation(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'driver_id' => 'required|string'
            ]);

            $location = $this->realtimeService->getDriverLocation($request->driver_id);

            return response()->json([
                'success' => true,
                'data' => $location
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get driver location: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get driver location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy tất cả driver online
     */
    public function getOnlineDrivers(): JsonResponse
    {
        try {
            $onlineDrivers = $this->realtimeService->getAllOnlineDrivers();

            return response()->json([
                'success' => true,
                'data' => $onlineDrivers,
                'count' => count($onlineDrivers)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get online drivers: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get online drivers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload file
     */
    public function uploadFile(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
                'path' => 'nullable|string'
            ]);

            $file = $request->file('file');
            $path = $request->input('path');

            $result = $this->storageService->uploadFile($file, $path);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload file'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to upload file: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gọi cloud function
     */
    public function executeFunction(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'function_id' => 'nullable|string',
                'data' => 'nullable|array',
                'type' => 'nullable|string|in:location,notification,default'
            ]);

            $functionId = $request->function_id ?: '686a1e4a0010de76b3ea';
            $data = $request->data ?? [];
            $type = $request->type ?? 'location';

            // If no data provided, use default payload
            if (empty($data)) {
                $data = $this->getDefaultPayload($type);
            }

            $result = $this->functionsService->executeFunction($functionId, $data);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Function executed successfully',
                    'data' => $result,
                    'function_id' => $functionId,
                    'type' => $type
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to execute function'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to execute function: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to execute function',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default payload based on type
     */
    private function getDefaultPayload($type)
    {
        switch ($type) {
            case 'location':
                return [
                    'driver_id' => 'test_driver_' . time(),
                    'location' => [
                        'latitude' => 10.762622,
                        'longitude' => 106.660172,
                        'speed' => 25.5,
                        'bearing' => 90,
                        'accuracy' => 10,
                        'isOnline' => true,
                        'status' => 'active'
                    ],
                    'action' => 'location_update',
                    'timestamp' => time()
                ];
                
            case 'notification':
                return [
                    'user_id' => 'test_user_' . time(),
                    'message' => 'Hello from Appwrite Function!',
                    'type' => 'info',
                    'action' => 'test_notification',
                    'timestamp' => time()
                ];
                
            default:
                return [
                    'test' => true,
                    'message' => 'Default test payload',
                    'timestamp' => time()
                ];
        }
    }

    /**
     * Gửi notification
     */
    public function sendNotification(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|string',
                'message' => 'required|string',
                'type' => 'nullable|string'
            ]);

            $result = $this->functionsService->sendUserNotification(
                $request->user_id,
                $request->message,
                $request->type ?? 'info'
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'data' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification'
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lấy thông tin Appwrite
     */
    public function getInfo(): JsonResponse
    {
        try {
            $functions = $this->functionsService->listFunctions();
            $onlineDrivers = $this->realtimeService->getAllOnlineDrivers();

            return response()->json([
                'success' => true,
                'data' => [
                    'functions_count' => count($functions),
                    'online_drivers_count' => count($onlineDrivers),
                    'config' => [
                        'project_id' => config('appwrite.project_id'),
                        'database_id' => config('appwrite.database_id'),
                        'storage_bucket_id' => config('appwrite.storage_bucket_id'),
                        'endpoint' => config('appwrite.endpoint')
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get Appwrite info: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get Appwrite info',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 