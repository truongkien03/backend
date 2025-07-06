<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use App\Events\OnlineDriversChanged;
use App\Jobs\ProcessOnlineDriversChange;

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
                ->withServiceAccount(storage_path('service_account.json'))
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
                    // Tự động gọi getAllOnlineDrivers khi có thay đổi
                    $onlineDrivers = $this->getAllOnlineDrivers();
                    
                    // Log số lượng driver online
                    Log::info('Auto-triggered: Found ' . count($onlineDrivers) . ' online drivers');
                    
                    // Xử lý từng driver
                    foreach ($data as $driverId => $locationData) {
                        $this->processLocationUpdate($driverId, $locationData);
                    }
                    
                    // Có thể thêm logic xử lý khác ở đây
                    $this->handleOnlineDriversChange($onlineDrivers);
                }
            });

            Log::info('Started listening to Firebase location changes with auto-trigger');
            
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
     * Xử lý khi danh sách online drivers thay đổi
     */
    private function handleOnlineDriversChange($onlineDrivers)
    {
        try {
            // In ra console danh sách driver online
            echo "\n🔄 Online Drivers Update:\n";
            echo "📊 Total Online: " . count($onlineDrivers) . "\n";
            
            foreach ($onlineDrivers as $driverId => $driverData) {
                echo "🚗 {$driverId}: Lat({$driverData['latitude']}), Lon({$driverData['longitude']})\n";
            }
            echo "----------------------------------------\n";
            
            // Dispatch event để các component khác có thể lắng nghe
            event(new OnlineDriversChanged($onlineDrivers));
            
            // Dispatch job để xử lý async
            dispatch(new ProcessOnlineDriversChange($onlineDrivers));
            
            // Có thể thêm logic khác:
            // - Gửi notification
            // - Cập nhật cache
            // - Trigger events
            // - Gửi WebSocket message
            
            // Ví dụ: Gửi notification cho admin
            $this->notifyAdminAboutOnlineDrivers($onlineDrivers);
            
        } catch (\Exception $e) {
            Log::error("Error handling online drivers change: " . $e->getMessage());
        }
    }

    /**
     * Thông báo cho admin về thay đổi driver online
     */
    private function notifyAdminAboutOnlineDrivers($onlineDrivers)
    {
        // Có thể implement notification logic ở đây
        // Ví dụ: gửi email, push notification, etc.
        Log::info("Admin notification: " . count($onlineDrivers) . " drivers are online");
    }

    /**
     * Lấy tất cả dữ liệu từ Firebase (debug)
     */
    public function getAllData()
    {
        try {
            $snapshot = $this->reference->getSnapshot();
            $allData = $snapshot->getValue();
            
            Log::info("All Firebase data: " . json_encode($allData));
            return $allData;
            
        } catch (\Exception $e) {
            Log::error("Failed to get all data: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lấy tất cả tọa độ driver đang online
     */
    public function getAllOnlineDrivers()
    {
        try {
            // Lấy tất cả dữ liệu trước
            $snapshot = $this->reference->getSnapshot();
            $allData = $snapshot->getValue();
            
            if (!$allData) {
                Log::info("No data found in Firebase realtime-locations");
                return [];
            }
            
            // Lọc ra các driver đang online
            $onlineDrivers = [];
            foreach ($allData as $driverId => $locationData) {
                if (isset($locationData['isOnline']) && $locationData['isOnline'] === true) {
                    $onlineDrivers[$driverId] = $locationData;
                }
            }
            
            Log::info("Found " . count($onlineDrivers) . " online drivers");
            return $onlineDrivers;
            
        } catch (\Exception $e) {
            Log::error("Failed to get online drivers: " . $e->getMessage());
            return [];
        }
    }
} 