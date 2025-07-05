<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirebaseRealtimeService
{
    private $database;
    private $reference;

    public function __construct()
    {
        $this->initializeFirebase();
    }

    private function initializeFirebase()
    {
        try {
            $factory = (new Factory)
                ->withServiceAccount(storage_path('firebase-credentials.json'))
                ->withDatabaseUri('https://delivery-0805-default-rtdb.firebaseio.com');

            $this->database = $factory->createDatabase();
            $this->reference = $this->database->getReference('realtime-locations');
            
            Log::info('Firebase Realtime Database connected successfully');
        } catch (\Exception $e) {
            Log::error('Firebase connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Lắng nghe thay đổi tọa độ realtime
     */
    public function listenToLocationChanges()
    {
        try {
            $this->reference->onValue(function ($snapshot) {
                $data = $snapshot->getValue();
                
                if ($data) {
                    foreach ($data as $driverId => $locationData) {
                        $this->processLocationUpdate($driverId, $locationData);
                    }
                }
            });

            Log::info('Started listening to Firebase location changes');
            
        } catch (\Exception $e) {
            Log::error('Error listening to Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Xử lý khi có thay đổi tọa độ
     */
    private function processLocationUpdate($driverId, $locationData)
    {
        $timestamp = date('Y-m-d H:i:s', $locationData['timestamp'] / 1000);
        
        $logMessage = sprintf(
            "🚗 Driver %s - Location Updated:\n" .
            "📍 Lat: %s, Lon: %s\n" .
            "⚡ Speed: %s km/h\n" .
            "🧭 Bearing: %s°\n" .
            "🎯 Accuracy: %s m\n" .
            "🟢 Online: %s\n" .
            "📊 Status: %s\n" .
            "⏰ Time: %s\n" .
            "----------------------------------------",
            $driverId,
            $locationData['latitude'],
            $locationData['longitude'],
            $locationData['speed'],
            $locationData['bearing'],
            $locationData['accuracy'],
            $locationData['isOnline'] ? 'Yes' : 'No',
            $locationData['status'],
            $timestamp
        );

        // In ra console
        echo $logMessage . "\n";
        
        // Log vào file
        Log::info("Driver location update: " . $driverId, $locationData);
        
        // Có thể thêm logic xử lý khác ở đây
        $this->handleLocationChange($driverId, $locationData);
    }

    /**
     * Xử lý logic khi tọa độ thay đổi
     */
    private function handleLocationChange($driverId, $locationData)
    {
        // Cập nhật database local nếu cần
        try {
            $driver = \App\Models\Driver::where('driver_id', $driverId)->first();
            
            if ($driver) {
                $driver->update([
                    'current_latitude' => $locationData['latitude'],
                    'current_longitude' => $locationData['longitude'],
                    'last_location_update' => now(),
                    'is_online' => $locationData['isOnline'],
                    'current_speed' => $locationData['speed'],
                    'current_bearing' => $locationData['bearing']
                ]);
                
                Log::info("Updated driver {$driverId} location in database");
            }
        } catch (\Exception $e) {
            Log::error("Failed to update driver location in database: " . $e->getMessage());
        }
    }

    /**
     * Lấy tọa độ hiện tại của driver
     */
    public function getDriverLocation($driverId)
    {
        try {
            $snapshot = $this->database->getReference("realtime-locations/{$driverId}")->getSnapshot();
            return $snapshot->getValue();
        } catch (\Exception $e) {
            Log::error("Failed to get driver location: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy tất cả tọa độ driver đang online
     */
    public function getAllOnlineDrivers()
    {
        try {
            $snapshot = $this->reference->orderByChild('isOnline')->equalTo(true)->getSnapshot();
            return $snapshot->getValue();
        } catch (\Exception $e) {
            Log::error("Failed to get online drivers: " . $e->getMessage());
            return [];
        }
    }
} 