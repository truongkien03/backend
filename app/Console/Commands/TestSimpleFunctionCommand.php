<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppwriteFunctionsService;
use Illuminate\Support\Facades\Log;

class TestSimpleFunctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appwrite:test-simple 
                            {function_id? : Function ID to test}
                            {--payload= : JSON payload to send}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test simple Appwrite function with minimal payload';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $functionId = $this->argument('function_id') ?: '686a1e4a0010de76b3ea';
        $payload = $this->option('payload');
        
        $this->info("🚀 Testing Simple Appwrite Function: {$functionId}");
        $this->newLine();
        
        try {
            $functionsService = new AppwriteFunctionsService();
            
            // Prepare simple test payload
            if ($payload) {
                $testData = json_decode($payload, true);
                if (!$testData) {
                    $this->error("Invalid JSON payload provided");
                    return 1;
                }
            } else {
                $testData = [
                    'test' => true,
                    'message' => 'Hello from Laravel!',
                    'timestamp' => time()
                ];
            }
            
            $this->info("📤 Sending simple payload:");
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
                $this->info("📊 Status: " . ($result['status'] ?? 'N/A'));
                $this->newLine();
                
                if ($result['status'] === 'completed') {
                    $this->info("🎉 Function completed successfully!");
                } elseif ($result['status'] === 'failed') {
                    $this->warn("⚠️ Function failed but executed");
                    if (!empty($result['errors'])) {
                        $this->error("Error: " . $result['errors']);
                    }
                }
                
                $this->info("📥 Full Response:");
                $this->line(json_encode($result, JSON_PRETTY_PRINT));
                
                // Log success
                Log::info("Simple Appwrite function test completed", [
                    'function_id' => $functionId,
                    'execution_time' => $executionTime,
                    'status' => $result['status'] ?? 'unknown'
                ]);
                
            } else {
                $this->error("❌ Function execution failed");
                Log::error("Simple Appwrite function test failed", [
                    'function_id' => $functionId
                ]);
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error testing function: " . $e->getMessage());
            Log::error("Simple Appwrite function test error", [
                'function_id' => $functionId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
        
        $this->newLine();
        $this->info("✅ Simple function testing completed!");
        
        return 0;
    }
} 