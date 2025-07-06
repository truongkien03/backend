<?php

use Appwrite\Extend\Exception;
use Utopia\App;
use Utopia\CLI\Console;

App::init(function (array $utopia, array $request, array $response, array $args) {
    /* 
     * Process Location Function
     * 
     * Function này xử lý location updates từ driver
     * Input: JSON với driver_id và location_data
     * Output: Kết quả xử lý
     */
    
    try {
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
        $driverId = $data['driver_id'] ?? null;
        $locationData = $data['location'] ?? null;
        $action = $data['action'] ?? 'location_update';
        $timestamp = $data['timestamp'] ?? time();
        
        if (!$driverId) {
            throw new Exception('driver_id is required', 400);
        }
        
        if (!$locationData) {
            throw new Exception('location data is required', 400);
        }
        
        // Validate location data
        $latitude = $locationData['latitude'] ?? null;
        $longitude = $locationData['longitude'] ?? null;
        $speed = $locationData['speed'] ?? 0;
        $bearing = $locationData['bearing'] ?? 0;
        $accuracy = $locationData['accuracy'] ?? 0;
        $isOnline = $locationData['isOnline'] ?? true;
        $status = $locationData['status'] ?? 'active';
        
        if ($latitude === null || $longitude === null) {
            throw new Exception('latitude and longitude are required', 400);
        }
        
        // Validate coordinates
        if ($latitude < -90 || $latitude > 90) {
            throw new Exception('Invalid latitude value', 400);
        }
        
        if ($longitude < -180 || $longitude > 180) {
            throw new Exception('Invalid longitude value', 400);
        }
        
        // Process location data
        $processedData = [
            'driver_id' => $driverId,
            'location_data' => [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
                'speed' => (float) $speed,
                'bearing' => (float) $bearing,
                'accuracy' => (float) $accuracy,
                'isOnline' => (bool) $isOnline,
                'status' => $status,
                'timestamp' => $timestamp * 1000 // Convert to milliseconds
            ],
            'processed_at' => date('Y-m-d H:i:s'),
            'action' => $action
        ];
        
        // Calculate additional data
        $processedData['location_data']['speed_kmh'] = round($speed * 3.6, 2); // Convert m/s to km/h
        $processedData['location_data']['speed_mph'] = round($speed * 2.237, 2); // Convert m/s to mph
        
        // Add geolocation info
        $processedData['location_data']['geolocation'] = [
            'country' => 'Vietnam',
            'city' => 'Ho Chi Minh City',
            'address' => "Lat: {$latitude}, Lon: {$longitude}"
        ];
        
        // Add processing metadata
        $processedData['metadata'] = [
            'function_id' => '686a1e4a0010de76b3ea',
            'function_name' => 'process-location',
            'processing_time' => microtime(true),
            'version' => '1.0.0'
        ];
        
        // Log processing
        Console::log("Processing location for driver: {$driverId}");
        Console::log("Location: {$latitude}, {$longitude}");
        Console::log("Speed: {$speed} m/s ({$processedData['location_data']['speed_kmh']} km/h)");
        Console::log("Online: " . ($isOnline ? 'Yes' : 'No'));
        
        // Return processed data
        return [
            'success' => true,
            'message' => 'Location processed successfully',
            'data' => $processedData,
            'timestamp' => time(),
            'function_execution_id' => uniqid()
        ];
        
    } catch (Exception $e) {
        Console::log("Error: " . $e->getMessage());
        throw $e;
    } catch (\Exception $e) {
        Console::log("Unexpected error: " . $e->getMessage());
        throw new Exception('Internal server error: ' . $e->getMessage(), 500);
    }
    
}, ['utopia', 'request', 'response', 'args']);

App::shutdown(function (array $utopia, array $request, array $response, array $args) {
    Console::log('Function execution completed');
}, ['utopia', 'request', 'response', 'args']); 