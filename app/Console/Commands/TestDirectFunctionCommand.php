<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestDirectFunctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appwrite:test-direct 
                            {--payload= : JSON payload to send}
                            {--domain= : Function domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Appwrite function directly via HTTP';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $payload = $this->option('payload') ?: '{"test":true}';
        $domain = $this->option('domain') ?: '686a1e4b00227f3d98a4.nyc.appwrite.run';
        
        $this->info("🚀 Testing Appwrite Function Directly");
        $this->info("Domain: {$domain}");
        $this->newLine();
        
        try {
            $this->info("📤 Sending payload:");
            $this->line($payload);
            $this->newLine();
            
            // Test direct HTTP call
            $startTime = microtime(true);
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Appwrite-Project' => config('appwrite.project_id'),
                    'X-Appwrite-Key' => config('appwrite.api_key')
                ])
                ->post("https://{$domain}", [
                    'payload' => $payload
                ]);
            
            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);
            
            $this->info("⏱️ Execution time: {$executionTime}ms");
            $this->info("📊 Status Code: " . $response->status());
            $this->newLine();
            
            if ($response->successful()) {
                $this->info("✅ Function executed successfully!");
                $this->info("📥 Response:");
                $this->line(json_encode($response->json(), JSON_PRETTY_PRINT));
                
                Log::info("Direct function test successful", [
                    'domain' => $domain,
                    'status_code' => $response->status(),
                    'execution_time' => $executionTime
                ]);
                
            } else {
                $this->error("❌ Function execution failed");
                $this->error("Status Code: " . $response->status());
                $this->error("Response: " . $response->body());
                
                Log::error("Direct function test failed", [
                    'domain' => $domain,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);
                
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error testing function: " . $e->getMessage());
            Log::error("Direct function test error", [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
        
        $this->newLine();
        $this->info("✅ Direct function testing completed!");
        
        return 0;
    }
} 