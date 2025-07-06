<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FcmV1Service;
use App\Models\Order;
use App\Models\Driver;

echo "Testing complete flow...\n";

// Test 1: FCM Service
$fcmService = app(FcmV1Service::class);
try {
    $result = $fcmService->sendToTopic(
        'all-drivers',
        'Test Thông báo',
        'Đây là test notification sau khi fix lỗi priority',
        ['type' => 'test', 'timestamp' => now()->toISOString()]
    );
    echo ($result ? "✅" : "❌") . " FCM Test: " . ($result ? "Success" : "Failed") . "\n";
} catch (Exception $e) {
    echo "❌ FCM Test Failed: " . $e->getMessage() . "\n";
}

// Test 2: Driver notification với đơn hàng
$order = Order::find(7);
$driver = Driver::find(1);

if ($order && $driver) {
    try {
        $driver->notify(new \App\Notifications\WaitForDriverConfirmation($order));
        echo "✅ Driver Notification: Success\n";
    } catch (Exception $e) {
        echo "❌ Driver Notification Failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Order hoặc Driver không tồn tại\n";
}

echo "\nTest completed!\n";
