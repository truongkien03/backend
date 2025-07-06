<?php

use Appwrite\ClamAV\Network;
use Appwrite\Extend\Exception;
use Appwrite\Extend\PDO;
use Appwrite\Utopia\Database;
use Appwrite\Utopia\Request;
use Appwrite\Utopia\Response;
use Utopia\App;
use Utopia\CLI\Console;
use Utopia\Config\Config;
use Utopia\Database\Document;
use Utopia\Database\Exception\Duplicate;
use Utopia\Database\Exception\Timeout;
use Utopia\Database\Exception\Unauthorized;
use Utopia\Database\Exception\Validator;
use Utopia\Database\Query;
use Utopia\Database\Validator\UID;
use Utopia\Exception\InvalidArgumentException;
use Utopia\Exception\InvalidValue;
use Utopia\Exception\Runtime;
use Utopia\Logger\Log;
use Utopia\Logger\Log\Audit;
use Utopia\Logger\Log\Tag;
use Utopia\System\System;
use Utopia\Validator\ArrayList;
use Utopia\Validator\Boolean;
use Utopia\Validator\Datetime as DatetimeValidator;
use Utopia\Validator\Email;
use Utopia\Validator\FloatValidator;
use Utopia\Validator\Integer;
use Utopia\Validator\JSON;
use Utopia\Validator\Numeric;
use Utopia\Validator\Range;
use Utopia\Validator\Text;
use Utopia\Validator\URL;
use Utopia\Validator\WhiteList;

App::init(function (array $utopia, array $request, array $response, array $args) {
    /* 
     * Send Notification Function
     * 
     * Function này gửi notification cho users/drivers
     * Input: JSON với user_id, message, type
     * Output: Kết quả gửi notification
     */
    
    $payload = $request['payload'] ?? null;
    
    if (!$payload) {
        throw new Exception('No payload provided', 400);
    }
    
    // Parse JSON payload
    $data = json_decode($payload, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON payload', 400);
    }
    
    // Validate required fields
    $userId = $data['user_id'] ?? null;
    $driverId = $data['driver_id'] ?? null;
    $message = $data['message'] ?? null;
    $type = $data['type'] ?? 'info';
    $action = $data['action'] ?? 'notification';
    $timestamp = $data['timestamp'] ?? time();
    
    if (!$message) {
        throw new Exception('message is required', 400);
    }
    
    if (!$userId && !$driverId) {
        throw new Exception('user_id or driver_id is required', 400);
    }
    
    // Validate notification type
    $validTypes = ['info', 'success', 'warning', 'error', 'order', 'location', 'system'];
    if (!in_array($type, $validTypes)) {
        $type = 'info';
    }
    
    // Process notification data
    $notificationData = [
        'recipient_id' => $userId ?: $driverId,
        'recipient_type' => $userId ? 'user' : 'driver',
        'message' => $message,
        'type' => $type,
        'action' => $action,
        'timestamp' => $timestamp,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];
    
    // Add notification metadata
    $notificationData['metadata'] = [
        'function_id' => 'send_notification_function_id',
        'function_name' => 'send-notification',
        'processing_time' => microtime(true),
        'version' => '1.0.0'
    ];
    
    // Simulate notification sending (trong thực tế sẽ gọi FCM, email, etc.)
    $notificationData['delivery_methods'] = [
        'push_notification' => true,
        'email' => false,
        'sms' => false,
        'in_app' => true
    ];
    
    // Add priority based on type
    $notificationData['priority'] = match($type) {
        'error' => 'high',
        'warning' => 'medium',
        'order' => 'high',
        default => 'normal'
    };
    
    // Log notification
    $recipient = $userId ?: $driverId;
    Console::log("Sending notification to: {$recipient}");
    Console::log("Message: {$message}");
    Console::log("Type: {$type}");
    Console::log("Priority: {$notificationData['priority']}");
    
    // Simulate delivery delay
    usleep(100000); // 0.1 seconds
    
    // Update status to delivered
    $notificationData['status'] = 'delivered';
    $notificationData['delivered_at'] = date('Y-m-d H:i:s');
    
    // Return notification result
    return [
        'success' => true,
        'message' => 'Notification sent successfully',
        'data' => $notificationData,
        'timestamp' => time(),
        'function_execution_id' => uniqid(),
        'delivery_status' => 'delivered'
    ];
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    /* 
     * Cleanup function
     * Được gọi sau khi function hoàn thành
     */
    
    Console::log('Notification function execution completed');
    
}, ['utopia', 'request', 'response', 'args']); 