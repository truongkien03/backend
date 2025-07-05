<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseRealtimeService;

class ListenFirebaseLocations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:listen-locations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to Firebase realtime location changes and log them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting Firebase Location Listener...');
        $this->info('📡 Listening to: https://delivery-0805-default-rtdb.firebaseio.com/realtime-locations');
        $this->info('⏰ Started at: ' . now());
        $this->info('----------------------------------------');

        try {
            $firebaseService = new FirebaseRealtimeService();
            $firebaseService->listenToLocationChanges();
            
            // Giữ script chạy
            while (true) {
                sleep(1);
            }
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
} 