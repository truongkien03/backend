<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Appwrite\Client;
use Appwrite\Services\Databases;
use Appwrite\Services\Realtime;
use App\Events\OnlineDriversChanged;
use App\Jobs\ProcessOnlineDriversChange;

class AppwriteRealtimeService
{
    private $client;
    private $databases;
    private $realtime;
    private $databaseId;
    private $locationsCollectionId;

    public function __construct()
    {
        $this->client = app(Client::class);
        $this->databases = app(Databases::class);
        $this->realtime = app(Realtime::class);
        $this->databaseId = config('appwrite.database_id');
        $this->locationsCollectionId = config('appwrite.collections.locations');
    }

    /**
     * Lắng nghe thay đổi tọa độ realtime từ Appwrite
     */
    public function listenToLocationChanges()
    {
        try {
            // Subscribe to realtime changes
            $this->realtime->subscribe(
                "databases.{$this->databaseId}.collections.{$this->locationsCollectionId}.documents",
                function ($response) {
                    $this->handleRealtimeUpdate($response);
                }
            );

            Log::info('Started listening to Appwrite location changes');
            
        } catch (\Exception $e) {
            Log::error('Error listening to Appwrite: ' . $e->getMessage());
        }
    }

    /**
     * Xử lý khi có thay đổi realtime
     */
    private function handleRealtimeUpdate($response)
    {
        try {
            $event = $response['events'][0] ?? null;
            $document = $response['payload'] ?? null;

            if (!$event || !$document) {
                return;
            }

            $driverId = $document['driver_id'] ?? null;
            $locationData = $document['location_data'] ?? null;

            if ($driverId && $locationData) {
                $this->processLocationUpdate($driverId, $locationData);
                
                // Tự động gọi getAllOnlineDrivers khi có thay đổi
                $onlineDrivers = $this->getAllOnlineDrivers();
                $this->handleOnlineDriversChange($onlineDrivers);
            }

        } catch (\Exception $e) {
            Log::error('Error handling realtime update: ' . $e->getMessage());
        }
    }

    /**
     * Xử lý khi có thay đổi tọa độ
     */
    private function processLocationUpdate($driverId, $locationData)
    {
        $timestamp = date('Y-m-d H:i:s', $locationData['timestamp'] / 1000);
        
        $logMessage = sprintf(
            "🚗 Driver %s - Location Updated (Appwrite):\n" .
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
        Log::info("Driver location update (Appwrite): " . $driverId, $locationData);
        
        // Cập nhật database local
        $this->handleLocationChange($driverId, $locationData);
    }

    /**
     * Xử lý logic khi tọa độ thay đổi
     */
    private function handleLocationChange($driverId, $locationData)
    {
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
                
                Log::info("Updated driver {$driverId} location in database (Appwrite)");
            }
        } catch (\Exception $e) {
            Log::error("Failed to update driver location in database: " . $e->getMessage());
        }
    }

    /**
     * Lấy tọa độ mới vào Appwrite
     */
    public function saveLocation($driverId, $locationData)
    {
        try {
            $documentId = $this->databases->createDocument(
                $this->databaseId,
                $this->locationsCollectionId,
                \Appwrite\ID::unique(),
                [
                    'driver_id' => $driverId,
                    'location_data' => $locationData,
                    'timestamp' => time() * 1000,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            Log::info("Saved location to Appwrite for driver: {$driverId}");
            return $documentId;

        } catch (\Exception $e) {
            Log::error("Failed to save location to Appwrite: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cập nhật tọa độ hiện tại
     */
    public function updateLocation($documentId, $locationData)
    {
        try {
            $this->databases->updateDocument(
                $this->databaseId,
                $this->locationsCollectionId,
                $documentId,
                [
                    'location_data' => $locationData,
                    'timestamp' => time() * 1000,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );

            Log::info("Updated location in Appwrite: {$documentId}");
            return true;

        } catch (\Exception $e) {
            Log::error("Failed to update location in Appwrite: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lấy tọa độ hiện tại của driver
     */
    public function getDriverLocation($driverId)
    {
        try {
            $documents = $this->databases->listDocuments(
                $this->databaseId,
                $this->locationsCollectionId,
                [
                    'queries' => [
                        \Appwrite\Query::equal('driver_id', $driverId),
                        \Appwrite\Query::orderDesc('timestamp'),
                        \Appwrite\Query::limit(1)
                    ]
                ]
            );

            if ($documents['documents']) {
                return $documents['documents'][0]['location_data'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Failed to get driver location from Appwrite: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Lấy tất cả tọa độ driver đang online
     */
    public function getAllOnlineDrivers()
    {
        try {
            $documents = $this->databases->listDocuments(
                $this->databaseId,
                $this->locationsCollectionId,
                [
                    'queries' => [
                        \Appwrite\Query::equal('location_data.isOnline', true),
                        \Appwrite\Query::orderDesc('timestamp')
                    ]
                ]
            );

            $onlineDrivers = [];
            foreach ($documents['documents'] as $document) {
                $driverId = $document['driver_id'];
                $locationData = $document['location_data'];
                
                // Chỉ lấy bản ghi mới nhất cho mỗi driver
                if (!isset($onlineDrivers[$driverId])) {
                    $onlineDrivers[$driverId] = $locationData;
                }
            }

            Log::info("Found " . count($onlineDrivers) . " online drivers in Appwrite");
            return $onlineDrivers;

        } catch (\Exception $e) {
            Log::error("Failed to get online drivers from Appwrite: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Xử lý khi danh sách online drivers thay đổi
     */
    private function handleOnlineDriversChange($onlineDrivers)
    {
        try {
            // In ra console danh sách driver online
            echo "\n🔄 Online Drivers Update (Appwrite):\n";
            echo "📊 Total Online: " . count($onlineDrivers) . "\n";
            
            foreach ($onlineDrivers as $driverId => $driverData) {
                echo "🚗 {$driverId}: Lat({$driverData['latitude']}), Lon({$driverData['longitude']})\n";
            }
            echo "----------------------------------------\n";
            
            // Dispatch event để các component khác có thể lắng nghe
            event(new OnlineDriversChanged($onlineDrivers));
            
            // Dispatch job để xử lý async
            dispatch(new ProcessOnlineDriversChange($onlineDrivers));
            
            // Thông báo cho admin
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
        Log::info("Admin notification (Appwrite): " . count($onlineDrivers) . " drivers are online");
    }

    /**
     * Lấy tất cả dữ liệu từ Appwrite (debug)
     */
    public function getAllData()
    {
        try {
            $documents = $this->databases->listDocuments(
                $this->databaseId,
                $this->locationsCollectionId
            );

            Log::info("All Appwrite data: " . json_encode($documents));
            return $documents;

        } catch (\Exception $e) {
            Log::error("Failed to get all data from Appwrite: " . $e->getMessage());
            return [];
        }
    }
} 