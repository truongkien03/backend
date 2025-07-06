<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StartLocationWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'location:worker {--queue=default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start queue worker for processing location updates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $queue = $this->option('queue');
        
        $this->info('🚀 Starting Location Update Worker...');
        $this->info("📡 Queue: {$queue}");
        $this->info('⏰ Started at: ' . now());
        $this->info('----------------------------------------');

        // Chạy queue worker
        $this->call('queue:work', [
            '--queue' => $queue,
            '--tries' => 3,
            '--timeout' => 60,
            '--memory' => 512
        ]);
    }
} 