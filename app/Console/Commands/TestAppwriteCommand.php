<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AppwriteRealtimeService;
use App\Services\AppwriteStorageService;
use App\Services\AppwriteFunctionsService;

class TestAppwriteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'appwrite:test {--service=all : Service to test (realtime, storage, functions, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Appwrite integration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $service = $this->option('service');
        
        $this->info('🚀 Testing Appwrite Integration...');
        $this->newLine();

        switch ($service) {
            case 'realtime':
                $this->testRealtimeService();
                break;
            case 'storage':
                $this->testStorageService();
                break;
            case 'functions':
                $this->testFunctionsService();
                break;
            case 'all':
            default:
                $this->testRealtimeService();
                $this->testStorageService();
                $this->testFunctionsService();
                break;
        }

        $this->info('✅ Appwrite testing completed!');
    }

    /**
     * Test Realtime Service
     */
    private function testRealtimeService()
    {
        $this->info('📡 Testing Appwrite Realtime Service...');
        
        try {
            $realtimeService = new AppwriteRealtimeService();
            
            // Test connection
            $allData = $realtimeService->getAllData();
            $this->info("✅ Connection successful. Found " . count($allData) . " records");
            
            // Test save location
            $testLocation = [
                'latitude' => 10.762622,
                'longitude' => 106.660172,
                'speed' => 25.5,
                'bearing' => 90,
                'accuracy' => 10,
                'isOnline' => true,
                'status' => 'active',
                'timestamp' => time() * 1000
            ];
            
            $documentId = $realtimeService->saveLocation('test_driver_001', $testLocation);
            if ($documentId) {
                $this->info("✅ Location saved successfully. Document ID: {$documentId}");
            } else {
                $this->warn("⚠️ Failed to save location");
            }
            
            // Test get online drivers
            $onlineDrivers = $realtimeService->getAllOnlineDrivers();
            $this->info("✅ Found " . count($onlineDrivers) . " online drivers");
            
        } catch (\Exception $e) {
            $this->error("❌ Realtime service test failed: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Test Storage Service
     */
    private function testStorageService()
    {
        $this->info('📁 Testing Appwrite Storage Service...');
        
        try {
            $storageService = new AppwriteStorageService();
            
            // Test list files
            $files = $storageService->listFiles();
            $this->info("✅ Storage connection successful. Found " . count($files) . " files");
            
            // Test upload from URL
            $testUrl = 'https://via.placeholder.com/150';
            $result = $storageService->uploadFileFromUrl($testUrl, 'test-image.jpg');
            
            if ($result) {
                $this->info("✅ File uploaded from URL successfully");
                
                // Test get file URL
                $fileUrl = $storageService->getFileUrl($result['$id']);
                if ($fileUrl) {
                    $this->info("✅ File URL retrieved: " . substr($fileUrl, 0, 50) . "...");
                }
            } else {
                $this->warn("⚠️ Failed to upload file from URL");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Storage service test failed: " . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Test Functions Service
     */
    private function testFunctionsService()
    {
        $this->info('⚡ Testing Appwrite Functions Service...');
        
        try {
            $functionsService = new AppwriteFunctionsService();
            
            // Test list functions
            $functions = $functionsService->listFunctions();
            $this->info("✅ Functions connection successful. Found " . count($functions) . " functions");
            
            if (count($functions) > 0) {
                // Test execute first function
                $firstFunction = $functions[0];
                $this->info("Testing function: " . $firstFunction['name']);
                
                $result = $functionsService->executeFunction($firstFunction['$id'], [
                    'test' => true,
                    'message' => 'Hello from Laravel!'
                ]);
                
                if ($result) {
                    $this->info("✅ Function executed successfully");
                } else {
                    $this->warn("⚠️ Function execution failed");
                }
            } else {
                $this->warn("⚠️ No functions found to test");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Functions service test failed: " . $e->getMessage());
        }
        
        $this->newLine();
    }
} 