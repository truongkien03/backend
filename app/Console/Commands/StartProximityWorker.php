<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LocationProximityService;
use App\Services\FirebaseRealtimeService;
use App\Jobs\ProcessProximityCheck;
use Illuminate\Support\Facades\Log;

class StartProximityWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proximity:worker 
                            {--radius=2.0 : Proximity radius in km}
                            {--interval=30 : Check interval in seconds}
                            {--firebase : Listen to Firebase realtime}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start proximity worker to monitor driver locations and send FCM notifications';

    private $proximityService;
    private $firebaseService;
    private $isRunning = true;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Proximity Worker...');
        $this->info('📏 Proximity radius: ' . $this->option('radius') . 'km');
        $this->info('⏱️  Check interval: ' . $this->option('interval') . ' seconds');
        $this->info('🔥 Firebase listening: ' . ($this->option('firebase') ? 'Yes' : 'No'));
        $this->info('⏰ Started at: ' . now());
        $this->info('================================');

        // Set up services
        $this->proximityService = app(LocationProximityService::class);
        $this->proximityService->setProximityRadius((float) $this->option('radius'));

        if ($this->option('firebase')) {
            $this->firebaseService = app(FirebaseRealtimeService::class);
        }

        // Set up signal handlers for graceful shutdown
        $this->setupSignalHandlers();

        try {
            if ($this->option('firebase')) {
                $this->startFirebaseListener();
            } else {
                $this->startPeriodicChecker();
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Proximity worker error: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Start Firebase realtime listener
     */
    private function startFirebaseListener()
    {
        $this->info('🔥 Starting Firebase realtime listener...');
        
        try {
            $this->firebaseService->listenToLocationChanges();
            
            // Keep the process running
            while ($this->isRunning) {
                sleep(1);
            }
        } catch (\Exception $e) {
            $this->error('Firebase listener error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start periodic checker
     */
    private function startPeriodicChecker()
    {
        $this->info('⏰ Starting periodic proximity checker...');
        
        $interval = (int) $this->option('interval');
        
        while ($this->isRunning) {
            try {
                $this->checkAllOnlineDrivers();
                $this->info("✅ Checked all online drivers at " . now()->format('H:i:s'));
                
                // Wait for next interval
                sleep($interval);
                
            } catch (\Exception $e) {
                $this->error('Periodic check error: ' . $e->getMessage());
                Log::error('Periodic proximity check error: ' . $e->getMessage());
                
                // Wait a bit before retrying
                sleep(5);
            }
        }
    }

    /**
     * Check all online drivers for proximity
     */
    private function checkAllOnlineDrivers()
    {
        $onlineDrivers = \App\Models\Driver::where('is_online', true)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->get();

        if ($onlineDrivers->isEmpty()) {
            $this->warn('No online drivers with location found');
            return;
        }

        $this->info("Found " . $onlineDrivers->count() . " online drivers to check");

        foreach ($onlineDrivers as $driver) {
            try {
                $locationData = [
                    'latitude' => $driver->current_latitude,
                    'longitude' => $driver->current_longitude,
                    'isOnline' => $driver->is_online,
                    'accuracy' => 10,
                    'speed' => $driver->current_speed ?? 0,
                    'bearing' => $driver->current_bearing ?? 0,
                    'timestamp' => time() * 1000
                ];

                // Dispatch proximity check job
                dispatch(new ProcessProximityCheck($driver->driver_id, $locationData));
                
                $this->line("  ✅ Queued proximity check for driver: {$driver->driver_id}");
                
            } catch (\Exception $e) {
                $this->error("  ❌ Error checking driver {$driver->driver_id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Set up signal handlers for graceful shutdown
     */
    private function setupSignalHandlers()
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }

    /**
     * Handle shutdown signal
     */
    public function shutdown()
    {
        $this->info('🛑 Shutting down proximity worker...');
        $this->isRunning = false;
    }
} 