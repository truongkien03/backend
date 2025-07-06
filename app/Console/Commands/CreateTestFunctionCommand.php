<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Appwrite\Client;
use Appwrite\Services\Functions;
use Illuminate\Support\Facades\Log;

class CreateTestFunctionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appwrite:create-test-function 
                            {--name=test-function : Function name}
                            {--runtime=php-8.0 : Function runtime}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new test function in Appwrite';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->option('name');
        $runtime = $this->option('runtime');
        
        $this->info("🚀 Creating new test function in Appwrite");
        $this->info("Name: {$name}");
        $this->info("Runtime: {$runtime}");
        $this->newLine();
        
        try {
            // Initialize Appwrite client
            $client = new Client();
            $client
                ->setEndpoint(config('appwrite.endpoint'))
                ->setProject(config('appwrite.project_id'))
                ->setKey(config('appwrite.api_key'));

            $functions = new Functions($client);
            
            // Create function
            $this->info("📝 Creating function...");
            $function = $functions->create(
                $name,
                ['http'],
                $runtime,
                'Test function for Laravel integration'
            );
            
            $functionId = $function['$id'];
            $this->info("✅ Function created successfully!");
            $this->info("Function ID: {$functionId}");
            $this->newLine();
            
            // Deploy simple code
            $this->info("📦 Deploying simple code...");
            $code = '<?php
use Utopia\App;

App::init(function (array $utopia, array $request, array $response, array $args) {
    return [
        "success" => true,
        "message" => "Test function working!",
        "timestamp" => time(),
        "function_id" => "' . $functionId . '"
    ];
}, ["utopia", "request", "response", "args"]);';
            
            $deployment = $functions->createDeployment(
                $functionId,
                'main',
                $code
            );
            
            $deploymentId = $deployment['$id'];
            $this->info("✅ Code deployed successfully!");
            $this->info("Deployment ID: {$deploymentId}");
            $this->newLine();
            
            // Update function to use new deployment
            $functions->updateDeployment(
                $functionId,
                $deploymentId
            );
            
            $this->info("✅ Function activated!");
            $this->newLine();
            
            // Test the new function
            $this->info("🧪 Testing new function...");
            $execution = $functions->createExecution(
                $functionId,
                json_encode(['test' => true])
            );
            
            $executionId = $execution['$id'];
            $this->info("✅ Function execution initiated!");
            $this->info("Execution ID: {$executionId}");
            $this->info("Status: " . ($execution['status'] ?? 'N/A'));
            $this->newLine();
            
            // Save function ID to config
            $this->info("💾 Updating configuration...");
            $this->updateConfig($functionId);
            
            $this->info("🎉 Test function created and tested successfully!");
            $this->info("Function ID: {$functionId}");
            $this->info("You can now use this function ID in your Laravel app");
            
            Log::info("Test function created successfully", [
                'function_id' => $functionId,
                'deployment_id' => $deploymentId,
                'execution_id' => $executionId
            ]);
            
        } catch (\Exception $e) {
            $this->error("❌ Error creating function: " . $e->getMessage());
            Log::error("Failed to create test function", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Update configuration with new function ID
     */
    private function updateConfig($functionId)
    {
        $configPath = config_path('appwrite.php');
        
        if (file_exists($configPath)) {
            $config = file_get_contents($configPath);
            
            // Update function ID
            $config = preg_replace(
                '/\'process_location\' => env\(\'APPWRITE_FUNCTION_PROCESS_LOCATION\', \'[^\']*\'\)/',
                "'process_location' => env('APPWRITE_FUNCTION_PROCESS_LOCATION', '{$functionId}')",
                $config
            );
            
            file_put_contents($configPath, $config);
            $this->info("✅ Configuration updated with new function ID");
        }
    }
} 