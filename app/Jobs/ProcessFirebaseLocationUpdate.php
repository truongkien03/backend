<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Tracker;
use App\Models\Driver;
use App\Events\LocationUpdated;

class ProcessFirebaseLocationUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $driverId;
    public $locationData;
    public $timestamp;

    /**
     * Create a new job instance.
     */
    public function __construct($driverId, $locationData)
    {
        $this->driverId = $driverId;
        $this->locationData = $locationData;
        $this->timestamp = now();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Processing Firebase location update for driver: {$this->driverId}", [
                'location_data' => $this->locationData
            ]);

            // Chuẩn bị dữ liệu tracker
            $trackerData = [
                'driver_id' => $this->driverId,
                'latitude' => $this->locationData['latitude'] ?? 0,
                'longitude' => $this->locationData['longitude'] ?? 0,
                'accuracy' => $this->locationData['accuracy'] ?? 0,
                'bearing' => $this->locationData['bearing'] ?? 0,
                'speed' => $this->locationData['speed'] ?? 0,
                'is_online' => $this->locationData['isOnline'] ?? false,
                'status' => $this->locationData['status'] ?? 0,
                'timestamp' => isset($this->locationData['timestamp']) 
                    ? date('Y-m-d H:i:s', $this->locationData['timestamp'] / 1000)
                    : now(),
                'updated_at' => now()
            ];

            // Cập nhật hoặc tạo tracker record
            $tracker = Tracker::updateOrCreate(
                ['driver_id' => $this->driverId],
                $trackerData
            );

            // Cập nhật thông tin driver
            $driver = Driver::where('driver_id', $this->driverId)->first();
            if ($driver) {
                $driver->update([
                    'current_latitude' => $trackerData['latitude'],
                    'current_longitude' => $trackerData['longitude'],
                    'is_online' => $trackerData['is_online'],
                    'last_location_update' => now()
                ]);
            }

            Log::info("Successfully updated tracker for driver: {$this->driverId}", [
                'tracker_id' => $tracker->id,
                'latitude' => $trackerData['latitude'],
                'longitude' => $trackerData['longitude']
            ]);

            // Broadcast event để các client khác có thể nhận realtime updates
            event(new LocationUpdated($this->driverId, $trackerData));

        } catch (\Exception $e) {
            Log::error("Error processing Firebase location update for driver: {$this->driverId}", [
                'error' => $e->getMessage(),
                'location_data' => $this->locationData
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Firebase location update job failed for driver: {$this->driverId}", [
            'error' => $exception->getMessage(),
            'location_data' => $this->locationData
        ]);
    }
}