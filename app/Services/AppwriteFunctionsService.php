<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Appwrite\Services\Functions;

class AppwriteFunctionsService
{
    private $functions;

    public function __construct()
    {
        $this->functions = app(Functions::class);
    }

    /**
     * Gọi cloud function để xử lý location
     */
    public function processLocation($locationData)
    {
        try {
            $functionId = config('appwrite.functions.process_location');
            
            // Validate function ID
            if (!$functionId || $functionId === 'process_location_function_id') {
                Log::warning("Function ID not configured, using default: 686a1e4a0010de76b3ea");
                $functionId = '686a1e4a0010de76b3ea';
            }
            
            $result = $this->functions->createExecution(
                $functionId,
                json_encode($locationData)
            );

            Log::info("Location processing function executed: " . $result['$id']);
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to execute location processing function: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gọi cloud function để gửi notification
     */
    public function sendNotification($notificationData)
    {
        try {
            $functionId = config('appwrite.functions.send_notification');
            
            $result = $this->functions->createExecution(
                $functionId,
                json_encode($notificationData)
            );

            Log::info("Notification function executed: " . $result['$id']);
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to execute notification function: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gọi cloud function tùy chỉnh
     */
    public function executeFunction($functionId, $data = [])
    {
        try {
            // Validate function ID
            if (!$functionId) {
                Log::error("Function ID is required");
                return null;
            }
            
            // Prepare payload
            $payload = json_encode($data);
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error("Invalid JSON payload: " . json_last_error_msg());
                return null;
            }
            
            Log::info("Executing function: {$functionId} with payload: {$payload}");
            
            $result = $this->functions->createExecution(
                $functionId,
                $payload
            );

            Log::info("Function execution initiated: " . $result['$id']);
            
            // Check execution status
            if (isset($result['status'])) {
                Log::info("Function status: " . $result['status']);
                
                if ($result['status'] === 'failed' && !empty($result['errors'])) {
                    Log::error("Function execution failed: " . $result['errors']);
                }
            }
            
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to execute function {$functionId}: " . $e->getMessage());
            Log::error("Exception trace: " . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Lấy danh sách functions
     */
    public function listFunctions()
    {
        try {
            $result = $this->functions->list();
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to list functions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy thông tin function
     */
    public function getFunction($functionId)
    {
        try {
            $result = $this->functions->get($functionId);
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to get function: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy logs của function
     */
    public function getFunctionLogs($functionId)
    {
        try {
            $result = $this->functions->listExecutions($functionId);
            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to get function logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Xử lý location update với cloud function
     */
    public function handleLocationUpdate($driverId, $locationData)
    {
        $data = [
            'driver_id' => $driverId,
            'location' => $locationData,
            'timestamp' => time(),
            'action' => 'location_update'
        ];

        return $this->processLocation($data);
    }

    /**
     * Gửi notification cho driver
     */
    public function sendDriverNotification($driverId, $message, $type = 'info')
    {
        $data = [
            'driver_id' => $driverId,
            'message' => $message,
            'type' => $type,
            'timestamp' => time(),
            'action' => 'driver_notification'
        ];

        return $this->sendNotification($data);
    }

    /**
     * Gửi notification cho user
     */
    public function sendUserNotification($userId, $message, $type = 'info')
    {
        $data = [
            'user_id' => $userId,
            'message' => $message,
            'type' => $type,
            'timestamp' => time(),
            'action' => 'user_notification'
        ];

        return $this->sendNotification($data);
    }

    /**
     * Xử lý order assignment
     */
    public function processOrderAssignment($orderId, $driverId)
    {
        $data = [
            'order_id' => $orderId,
            'driver_id' => $driverId,
            'timestamp' => time(),
            'action' => 'order_assignment'
        ];

        return $this->executeFunction(
            config('appwrite.functions.process_location'),
            $data
        );
    }
} 