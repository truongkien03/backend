<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppwriteFunctionsService;
use Illuminate\Support\Facades\Log;

class TestAppwriteFunctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appwrite:test-function 
                            {function_id? : Function ID to test}
                            {--payload= : JSON payload to send}
                            {--type=location : Type of test (location, notification)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test specific Appwrite function with custom payload';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $functionId = $this->argument('function_id') ?: '686a1e4a0010de76b3ea';
        $payload = $this->option('payload');
        $type = $this->option('type');
        
        $this->info("🚀 Testing Appwrite Function: {$functionId}");
        $this->info("Type: {$type}");
        $this->newLine();
        
        try {
            $functionsService = new AppwriteFunctionsService();
            
            // Prepare test payload based on type
            if ($payload) {
                $testData = json_decode($payload, true);
                if (!$testData) {
                    $this->error("Invalid JSON payload provided");
                    return 1;
                }
            } else {
                $testData = $this->getDefaultPayload($type);
            }
            
            $this->info("📤 Sending payload:");
            $this->line(json_encode($testData, JSON_PRETTY_PRINT));
            $this->newLine();
            
            // Execute function
            $startTime = microtime(true);
            $result = $functionsService->executeFunction($functionId, $testData);
            $endTime = microtime(true);
            
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            if ($result) {
                $this->info("✅ Function executed successfully!");
                $this->info("⏱️ Execution time: {$executionTime}ms");
                $this->info("🆔 Execution ID: " . ($result['$id'] ?? 'N/A'));
                $this->newLine();
                
                $this->info("📥 Response:");
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                
                // Log success
                Log::info("Appwrite function test successful", [
                    'function_id' => $functionId,
                    'type' => $type,
                    'execution_time' => $executionTime,
                    'result' => $result
                ]);
                
            } else {
                $this->error("❌ Function execution failed");
                Log::error("Appwrite function test failed", [
                    'function_id' => $functionId,
                    'type' => $type
                ]);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error testing function: " . $e->getMessage());
            Log::error("Appwrite function test error", [
                'function_id' => $functionId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
        
        $this->newLine();
        $this->info("✅ Function testing completed!");
        
        return 0;
    }
    
    /**
     * Get default payload based on type
     */
    private function getDefaultPayload($type)
    {
        switch ($type) {
            case 'location':
                return [
                    'driver_id' => 'test_driver_' . time(),
                    'location' => [
                        'latitude' => 10.762622,
                        'longitude' => 106.660172,
                        'speed' => 25.5,
                        'bearing' => 90,
                        'accuracy' => 10,
                        'isOnline' => true,
                        'status' => 'active'
                    ],
                    'action' => 'location_update',
                    'timestamp' => time()
                ];
                
            case 'notification':
                return [
                    'user_id' => 'test_user_' . time(),
                    'message' => 'Hello from Appwrite Function!',
                    'type' => 'info',
                    'action' => 'test_notification',
                    'timestamp' => time()
                ];
                
            default:
                return [
                    'test' => true,
                    'message' => 'Default test payload',
                    'timestamp' => time()
                ];
        }
    }
} 