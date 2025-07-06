<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:create-token {name=firebase-function}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create API token for Firebase Function';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');
        $token = Str::random(60);
        
        // Lưu token vào config hoặc database
        $this->info("API Token created successfully!");
        $this->info("Name: {$name}");
        $this->info("Token: {$token}");
        $this->info("");
        $this->info("Add this to your .env file:");
        $this->info("FIREBASE_API_TOKEN={$token}");
        $this->info("");
        $this->info("Or use directly in Firebase Function:");
        $this->info("'Authorization': 'Bearer {$token}'");
        
        // Lưu vào file config
        $configPath = config_path('firebase.php');
        if (!file_exists($configPath)) {
            $this->createFirebaseConfig($token);
        }
        
        return 0;
    }
    
    private function createFirebaseConfig($token)
    {
        $config = "<?php\n\nreturn [\n";
        $config .= "    'api_token' => '{$token}',\n";
        $config .= "    'allowed_ips' => [\n";
        $config .= "        // Thêm IP của Firebase Functions nếu cần\n";
        $config .= "    ],\n";
        $config .= "];\n";
        
        file_put_contents($configPath, $config);
        $this->info("Firebase config created at: {$configPath}");
    }
} 