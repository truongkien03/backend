<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessOnlineDriversChange implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $onlineDrivers;
    public $timestamp;

    /**
     * Create a new job instance.
     */
    public function __construct($onlineDrivers)
    {
        $this->onlineDrivers = $onlineDrivers;
        $this->timestamp = now();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Processing online drivers change job", [
                'count' => count($this->onlineDrivers),
                'timestamp' => $this->timestamp
            ]);

            // Xử lý logic khi có thay đổi online drivers
            $this->processOnlineDriversChange();
            
        } catch (\Exception $e) {
            Log::error("Error processing online drivers change job: " . $e->getMessage());
        }
    }

    /**
     * Xử lý logic khi danh sách online drivers thay đổi
     */
    private function processOnlineDriversChange()
    {
        // 1. Cập nhật cache
        cache()->put('online_drivers', $this->onlineDrivers, now()->addMinutes(5));
        
        // 2. Gửi notification cho admin nếu cần
        if (count($this->onlineDrivers) === 0) {
            Log::warning("No drivers are currently online");
        }
        
        // 3. Cập nhật statistics
        $this->updateStatistics();
        
        // 4. Có thể thêm logic khác tùy theo yêu cầu
    }

    /**
     * Cập nhật thống kê
     */
    private function updateStatistics()
    {
        $stats = [
            'total_online' => count($this->onlineDrivers),
            'last_updated' => now(),
            'driver_ids' => array_keys($this->onlineDrivers)
        ];
        
        cache()->put('driver_statistics', $stats, now()->addMinutes(10));
        
        Log::info("Updated driver statistics", $stats);
    }
} 