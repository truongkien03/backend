<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppwriteFunctionsService;
use Illuminate\Support\Facades\Log;

class TestFunctionPermissionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appwrite:test-permissions 
                            {function_id? : Function ID to test}
                            {--check-config : Check Appwrite configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Appwrite function permissions and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $functionId = $this->argument('function_id') ?: '686a1e4a0010de76b3ea';
        $checkConfig = $this->option('check-config');
        
        $this->info("🔍 Testing Appwrite Function Permissions");
        $this->info("Function ID: {$functionId}");
        $this->newLine();
        
        try {
            $functionsService = new AppwriteFunctionsService();
            
            // Check configuration
            if ($checkConfig) {
                $this->checkConfiguration();
            }
            
            // Test function info
            $this->info("📋 Getting function information...");
            $functionInfo = $functionsService->getFunction($functionId);
            
            if ($functionInfo) {
                $this->info("✅ Function found!");
                $this->info("Name: " . ($functionInfo['name'] ?? 'N/A'));
                $this->info("Status: " . ($functionInfo['status'] ?? 'N/A'));
                $this->info("Runtime: " . ($functionInfo['runtime'] ?? 'N/A'));
                $this->newLine();
                
                // Check function logs
                $this->info("📊 Getting function logs...");
                $logs = $functionsService->getFunctionLogs($functionId);
                
                if ($logs && !empty($logs['executions'])) {
                    $this->info("Found " . count($logs['executions']) . " executions");
                    
                    $latestExecution = $logs['executions'][0] ?? null;
                    if ($latestExecution) {
                        $this->info("Latest execution:");
                        $this->info("  ID: " . ($latestExecution['$id'] ?? 'N/A'));
                        $this->info("  Status: " . ($latestExecution['status'] ?? 'N/A'));
                        $this->info("  Duration: " . ($latestExecution['duration'] ?? 'N/A') . "s");
                        
                        if (!empty($latestExecution['errors'])) {
                            $this->error("  Errors: " . $latestExecution['errors']);
                        }
                    }
                } else {
                    $this->warn("No execution logs found");
                }
                
            } else {
                $this->error("❌ Function not found or not accessible");
                return 1;
            }
            
            // Test simple execution
            $this->newLine();
            $this->info("🚀 Testing simple execution...");
            
            $testData = [
                'test' => true,
                'message' => 'Permission test',
                'timestamp' => time()
            ];
            
            $result = $functionsService->executeFunction($functionId, $testData);
            
            if ($result) {
                $this->info("✅ Function execution initiated!");
                $this->info("Execution ID: " . ($result['$id'] ?? 'N/A'));
                $this->info("Status: " . ($result['status'] ?? 'N/A'));
                
                if ($result['status'] === 'completed') {
                    $this->info("🎉 Function completed successfully!");
                } elseif ($result['status'] === 'failed') {
                    $this->warn("⚠️ Function failed");
                    if (!empty($result['errors'])) {
                        $this->error("Error: " . $result['errors']);
                    }
                }
                
                Log::info("Function permissions test completed", [
                    'function_id' => $functionId,
                    'status' => $result['status'] ?? 'unknown'
                ]);
                
            } else {
                $this->error("❌ Function execution failed");
                return 1;
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Error testing permissions: " . $e->getMessage());
            Log::error("Function permissions test error", [
                'function_id' => $functionId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
        
        $this->newLine();
        $this->info("✅ Permissions testing completed!");
        
        return 0;
    }
    
    /**
     * Check Appwrite configuration
     */
    private function checkConfiguration()
    {
        $this->info("🔧 Checking Appwrite configuration...");
        
        $configs = [
            'project_id' => config('appwrite.project_id'),
            'endpoint' => config('appwrite.endpoint'),
            'api_key' => config('appwrite.api_key'),
            'database_id' => config('appwrite.database_id'),
            'storage_bucket_id' => config('appwrite.storage_bucket_id')
        ];
        
        foreach ($configs as $key => $value) {
            if (empty($value) || $value === 'your_' . $key . '_here') {
                $this->error("❌ {$key}: Not configured");
            } else {
                $this->info("✅ {$key}: " . substr($value, 0, 20) . "...");
            }
        }
        
        $this->newLine();
    }
} 