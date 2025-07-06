<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Services\LocationProximityService;

class ProcessProximityCheck implements ShouldQueue
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
            Log::info("Processing proximity check for driver: {$this->driverId}", [
                'latitude' => $this->locationData['latitude'],
                'longitude' => $this->locationData['longitude'],
                'is_online' => $this->locationData['isOnline'] ?? false
            ]);

            // Chỉ xử lý nếu driver online
            if (!($this->locationData['isOnline'] ?? false)) {
                Log::info("Driver {$this->driverId} is offline, skipping proximity check");
                return;
            }

            $proximityService = app(LocationProximityService::class);
            $proximityService->processDriverLocationUpdate($this->driverId, $this->locationData);

            Log::info("Successfully processed proximity check for driver: {$this->driverId}");

        } catch (\Exception $e) {
            Log::error("Error processing proximity check for driver: {$this->driverId}", [
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
        Log::error("Proximity check job failed for driver: {$this->driverId}", [
            'error' => $exception->getMessage(),
            'location_data' => $this->locationData
        ]);
    }
} 