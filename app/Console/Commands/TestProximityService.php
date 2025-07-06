<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\LocationProximityService;
use App\Models\Order;
use App\Models\Driver;

class TestProximityService extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:proximity 
                            {--driver-id= : Driver ID to test}
                            {--lat= : Latitude for testing}
                            {--lon= : Longitude for testing}
                            {--radius=2.0 : Proximity radius in km}
                            {--list-orders : List all pending orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test proximity service functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Proximity Service...');
        $this->info('================================');

        $proximityService = app(LocationProximityService::class);

        // Set custom radius if provided
        if ($this->option('radius')) {
            $proximityService->setProximityRadius((float) $this->option('radius'));
            $this->info("📏 Set proximity radius to: " . $this->option('radius') . "km");
        }

        // List all pending orders
        if ($this->option('list-orders')) {
            $this->listPendingOrders();
            return;
        }

        // Test with specific coordinates
        if ($this->option('lat') && $this->option('lon')) {
            $this->testWithCoordinates(
                (float) $this->option('lat'),
                (float) $this->option('lon'),
                $proximityService
            );
            return;
        }

        // Test with specific driver
        if ($this->option('driver-id')) {
            $this->testWithDriver($this->option('driver-id'), $proximityService);
            return;
        }

        // Default test with sample coordinates
        $this->testWithSampleData($proximityService);
    }

    /**
     * List all pending orders
     */
    private function listPendingOrders()
    {
        $this->info('📋 Listing all pending orders:');
        $this->info('-------------------------------');

        $orders = Order::whereNull('driver_id')
            ->where('status_code', config('const.order.status.pending', 1))
            ->get();

        if ($orders->isEmpty()) {
            $this->warn('No pending orders found');
            return;
        }

        $headers = ['ID', 'From Address', 'To Address', 'Distance', 'Created At'];
        $rows = [];

        foreach ($orders as $order) {
            $rows[] = [
                $order->id,
                $order->from_address['desc'] ?? 'N/A',
                $order->to_address['desc'] ?? 'N/A',
                $order->distance . 'km',
                $order->created_at->format('Y-m-d H:i:s')
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Test with specific coordinates
     */
    private function testWithCoordinates($lat, $lon, $proximityService)
    {
        $this->info("📍 Testing with coordinates: {$lat}, {$lon}");
        $this->info('----------------------------------------');

        $locationData = [
            'latitude' => $lat,
            'longitude' => $lon,
            'isOnline' => true,
            'accuracy' => 10,
            'speed' => 0,
            'bearing' => 0,
            'timestamp' => time() * 1000
        ];

        $nearbyOrders = $proximityService->findNearbyOrders($lat, $lon);

        if ($nearbyOrders->isEmpty()) {
            $this->warn('No nearby orders found');
            return;
        }

        $this->info("Found " . $nearbyOrders->count() . " nearby orders:");
        $this->info('');

        $headers = ['Order ID', 'From Address', 'To Address', 'Distance', 'Shipping Cost'];
        $rows = [];

        foreach ($nearbyOrders as $order) {
            $rows[] = [
                $order->id,
                $order->from_address['desc'] ?? 'N/A',
                $order->to_address['desc'] ?? 'N/A',
                number_format($order->distance, 2) . 'km',
                number_format($order->shipping_cost) . ' VND'
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Test with specific driver
     */
    private function testWithDriver($driverId, $proximityService)
    {
        $this->info("🚗 Testing with driver: {$driverId}");
        $this->info('----------------------------------------');

        $driver = Driver::where('driver_id', $driverId)->first();
        if (!$driver) {
            $this->error("Driver not found: {$driverId}");
            return;
        }

        if (!$driver->current_latitude || !$driver->current_longitude) {
            $this->warn("Driver {$driverId} has no current location");
            return;
        }

        $locationData = [
            'latitude' => $driver->current_latitude,
            'longitude' => $driver->current_longitude,
            'isOnline' => $driver->is_online,
            'accuracy' => 10,
            'speed' => $driver->current_speed ?? 0,
            'bearing' => $driver->current_bearing ?? 0,
            'timestamp' => time() * 1000
        ];

        $this->info("Driver location: {$driver->current_latitude}, {$driver->current_longitude}");
        $this->info("Driver online: " . ($driver->is_online ? 'Yes' : 'No'));

        $nearbyOrders = $proximityService->findNearbyOrders(
            $driver->current_latitude,
            $driver->current_longitude
        );

        if ($nearbyOrders->isEmpty()) {
            $this->warn('No nearby orders found for this driver');
            return;
        }

        $this->info("Found " . $nearbyOrders->count() . " nearby orders for driver:");
        $this->info('');

        $headers = ['Order ID', 'From Address', 'To Address', 'Distance', 'Shipping Cost'];
        $rows = [];

        foreach ($nearbyOrders as $order) {
            $rows[] = [
                $order->id,
                $order->from_address['desc'] ?? 'N/A',
                $order->to_address['desc'] ?? 'N/A',
                number_format($order->distance, 2) . 'km',
                number_format($order->shipping_cost) . ' VND'
            ];
        }

        $this->table($headers, $rows);
    }

    /**
     * Test with sample data
     */
    private function testWithSampleData($proximityService)
    {
        $this->info('🧪 Testing with sample data (Ho Chi Minh City coordinates)');
        $this->info('--------------------------------------------------------');

        // Sample coordinates in Ho Chi Minh City
        $sampleLat = 10.8231;
        $sampleLon = 106.6297;

        $this->testWithCoordinates($sampleLat, $sampleLon, $proximityService);
    }
} 