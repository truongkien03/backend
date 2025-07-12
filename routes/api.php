<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginWithOtpController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginWithPasswordController;
use App\Http\Controllers\Api\CurrentLocationController;
use App\Http\Controllers\Api\Driver\Auth\LoginController;
use App\Http\Controllers\Api\Driver\Auth\RegisterController as DriverRegisterController;
use App\Http\Controllers\Api\Driver\Auth\SendOtpController as DriverSendOtpController;
use App\Http\Controllers\Api\Driver\OrderController as AppOrderController;
use App\Http\Controllers\Api\Driver\ProfileController as DriverProfileController;
use App\Http\Controllers\Api\Driver\SharingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\FcmController;
use App\Http\Controllers\Api\FirebaseLocationController;
use App\Http\Controllers\Api\TrackerController;
use App\Http\Controllers\Api\AppwriteController;
use App\Http\Controllers\Api\ProximityController;
use App\Http\Controllers\Api\Driver\OrderProofImageController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('register', [RegisterController::class, 'register']);
Route::post('register/otp', [RegisterController::class, 'sendOtpForRegister']);
Route::post('login', [LoginWithOtpController::class, 'login']);
Route::post('login/otp', [LoginWithOtpController::class, 'sendOtp']);
Route::post('login/password', LoginWithPasswordController::class);
Route::post('password/reset', [ProfileController::class, 'resetPassword']);
Route::post('password/forgot', [ProfileController::class, 'forgotPassword']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/fcm/token', [FcmController::class, 'addFcmToken']);
    Route::delete('/fcm/token', [FcmController::class, 'removeFcmToken']);
    Route::get('/notifications', [ProfileController::class, 'notifications']);
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::post('/password', [ProfileController::class, 'changePassword']);
    Route::post('/set-password', [ProfileController::class, 'setInitialPassword']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'changeAvatar']);
    Route::get('/orders/inproccess', [OrderController::class, 'getInProcessList']);
    Route::get('/orders/completed', [OrderController::class, 'getCompleteList']);
    Route::post('/shipping-fee', [OrderController::class, 'calculateShippingFee']);
    Route::get('/route', [OrderController::class, 'getRoute']);
    Route::get('/orders/{order}', [OrderController::class, 'detail']);
    Route::get('/orders/{order}/drivers/recommended', [OrderController::class, 'getRecommendedDriver']);
    Route::post('/orders/{order}/drivers', [OrderController::class, 'updateDriver']);
    Route::post('/orders/{order}/drivers/random', [OrderController::class, 'updateRandomDriver']);
    Route::post('/orders/{order}/review', [OrderController::class, 'reviewDriver']);
    Route::post('/orders', [OrderController::class, 'createOrder']);
});

Route::prefix('driver')->group(function () {
    Route::post('/register/otp', [DriverRegisterController::class, 'sendOtpForRegister']);
    Route::post('/register', [DriverRegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'loginWithOtp']);
    Route::post('/login/otp', [LoginController::class, 'sendOtp']);
    Route::post('/login/password', [LoginController::class, 'loginWithPassword']);
    Route::post('/finding', [SharingController::class, 'find']);
    Route::post('/sharing-group', [SharingController::class, 'addToSharingGroup']);
    Route::get('/sharing-group', [SharingController::class, 'sharingList']);

    Route::middleware(['auth:driver'])->group(function () {
        Route::get('/profile', [DriverProfileController::class, 'profile']);
        Route::post('/profile', [DriverProfileController::class, 'updateProfile']);
        Route::post('/profile/avatar', [DriverProfileController::class, 'changeAvatar']);
        Route::post('/setting/status/online', [DriverProfileController::class, 'setStatusOnline']);
        Route::post('/setting/status/offline', [DriverProfileController::class, 'setStatusOffline']);
        Route::post('/fcm/token', [DriverProfileController::class, 'addFcmToken']);
        Route::delete('/fcm/token', [DriverProfileController::class, 'removeFcmToken']);
        Route::get('/notifications', [DriverProfileController::class, 'notifications']);
        Route::post('/set-password', \App\Http\Controllers\Api\Driver\Auth\SetPasswordController::class);
        Route::post('/change-password', \App\Http\Controllers\Api\Driver\Auth\ChangePasswordController::class);

        Route::middleware(['profileVerified'])->group(function () {
            Route::post('current-location', [CurrentLocationController::class, 'updateLocation']);
            Route::get('/orders/summary', [AppOrderController::class, 'summary']);
            Route::get('/orders/my-orders', [AppOrderController::class, 'getMyOrders']);
            Route::get('/orders/inprocess', [AppOrderController::class, 'getInProcessOrders']);
            Route::get('/orders/completed', [AppOrderController::class, 'getCompletedOrders']);
            Route::get('/orders/available', [AppOrderController::class, 'getAvailableOrders']);
            Route::get('/orders/delivery-history', [AppOrderController::class, 'getDeliveryHistory']);
            Route::post('/orders/{order}/accept', [AppOrderController::class, 'acceptOrder']);
            Route::post('/orders/{order}/decline', [AppOrderController::class, 'declineOrder']);
            Route::post('/orders/{order}/complete', [AppOrderController::class, 'conpleteOrder']);
            Route::post('/orders/{order}/arrived', [AppOrderController::class, 'arrivedAtDestination']);
            Route::get('/orders/{order}', [AppOrderController::class, 'detail']);
            Route::get('/orders/{order}/delivery-details', [AppOrderController::class, 'getDeliveryDetails']);
            Route::post('/orders/{order}/drivers/sharing', [AppOrderController::class, 'orderSharing']);
            Route::post('/orders/{order}/drivers/sharing/accept', [AppOrderController::class, 'acceptOrderSharing']);
            Route::post('/orders/{order}/drivers/sharing/decline', [AppOrderController::class, 'declineOrderSharing']);
            Route::get('/drive/order-pending/active-list', [AppOrderController::class, 'getActiveOrders']);
            Route::get('/drive/order-pending/completed-list', [AppOrderController::class, 'getCompletedOrdersCustom']);
            Route::get('/drive/order-pending/cancelled-list', [AppOrderController::class, 'getCancelledOrdersCustom']);
            Route::get('/drive/order-pending/arriving-list', [AppOrderController::class, 'getArrivingOrdersCustom']);
            Route::post('/order-proof-image', [OrderProofImageController::class, 'store']);
            Route::get('/statistics/earnings', [\App\Http\Controllers\Api\Driver\StatisticsController::class, 'earningsStatistics']);
            Route::get('/statistics/shipper', [\App\Http\Controllers\Api\Driver\StatisticsController::class, 'shipperStatistics']);
        });
    });
});

// Test routes for debugging
Route::get('/test/queue-status', function() {
    $jobsCount = DB::table('jobs')->count();
    $recentJobs = DB::table('jobs')->orderBy('id', 'desc')->limit(5)->get();
    
    return response()->json([
        'queue_connection' => config('queue.default'),
        'jobs_count' => $jobsCount,
        'recent_jobs' => $recentJobs,
        'failed_jobs_count' => DB::table('failed_jobs')->count()
    ]);
});

Route::get('/test/dispatch-job/{orderId}', function($orderId) {
    $order = \App\Models\Order::find($orderId);
    if (!$order) {
        return response()->json(['error' => 'Order not found'], 404);
    }
    
    \App\Jobs\FindRandomDriverForOrder::dispatch($order);
    
    return response()->json([
        'message' => "Job dispatched for order {$orderId}",
        'order' => $order
    ]);
});

Route::get('/test/drivers-status', function() {
    $drivers = \App\Models\Driver::with('profile')->get();
    
    return response()->json([
        'total_drivers' => $drivers->count(),
        'free_drivers' => $drivers->where('status', 1)->count(),
        'drivers_with_profile' => $drivers->filter(function($driver) {
            return $driver->profile !== null;
        })->count(),
        'drivers' => $drivers->map(function($driver) {
            return [
                'id' => $driver->id,
                'name' => $driver->name,
                'status' => $driver->status,
                'has_profile' => $driver->profile !== null,
                'fcm_token' => $driver->fcm_token ? 'Yes' : 'No'
            ];
        })
    ]);
});

// Test FCM for driver
Route::get('/test/driver-fcm/{driverId}', function($driverId) {
    $driver = \App\Models\Driver::find($driverId);
    if (!$driver) {
        return response()->json(['error' => 'Driver not found'], 404);
    }
    
    $fcmService = app(\App\Services\FcmV1Service::class);
    
    $result = $fcmService->sendToToken(
        $driver->fcm_token,
        'Test Notification',
        'Đây là test notification cho driver',
        [
            'type' => 'test',
            'timestamp' => now()->toISOString()
        ]
    );
    
    return response()->json([
        'driver_id' => $driver->id,
        'driver_name' => $driver->name,
        'fcm_token' => $driver->fcm_token ? substr($driver->fcm_token, 0, 20) . '...' : 'NULL',
        'fcm_sent' => $result,
        'message' => $result ? 'FCM sent successfully' : 'FCM failed'
    ]);
});

// Test FCM for customer
Route::get('/test/customer-fcm/{customerId}', function($customerId) {
    $customer = \App\Models\User::find($customerId);
    if (!$customer) {
        return response()->json(['error' => 'Customer not found'], 404);
    }
    
    $fcmService = app(\App\Services\FcmV1Service::class);
    
    $result = $fcmService->sendToToken(
        $customer->fcm_token,
        'Test Notification',
        'Đây là test notification cho customer',
        [
            'type' => 'test',
            'timestamp' => now()->toISOString()
        ]
    );
    
    return response()->json([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'fcm_token' => $customer->fcm_token ? substr($customer->fcm_token, 0, 20) . '...' : 'NULL',
        'fcm_sent' => $result,
        'message' => $result ? 'FCM sent successfully' : 'FCM failed'
    ]);
});

// Tracker APIs
Route::prefix('tracker')->group(function () {
    Route::post('/update', [TrackerController::class, 'updateFromApp']); // Flutter app gọi trực tiếp
    Route::post('/update-firebase', [TrackerController::class, 'updateFromFirebase'])->middleware('firebase.auth'); // Firebase Function (nếu có)
    Route::get('/online', [TrackerController::class, 'getOnlineTrackers']);
    Route::get('/all-locations', [TrackerController::class, 'getAllDriverLocations']); // Tất cả driver locations
    Route::get('/driver/{driverId}', [TrackerController::class, 'getDriverTracker']);
    Route::get('/driver/{driverId}/history', [TrackerController::class, 'getDriverHistory']);
});

// Appwrite APIs
Route::prefix('appwrite')->group(function () {
    Route::get('/test-connection', [AppwriteController::class, 'testConnection']);
    Route::get('/info', [AppwriteController::class, 'getInfo']);
    Route::post('/location/save', [AppwriteController::class, 'saveLocation']);
    Route::get('/location/driver', [AppwriteController::class, 'getDriverLocation']);
    Route::get('/drivers/online', [AppwriteController::class, 'getOnlineDrivers']);
    Route::post('/file/upload', [AppwriteController::class, 'uploadFile']);
    Route::post('/function/execute', [AppwriteController::class, 'executeFunction']);
    Route::post('/notification/send', [AppwriteController::class, 'sendNotification']);
});

// Proximity APIs
Route::prefix('proximity')->group(function () {
    Route::get('/nearby-orders', [ProximityController::class, 'findNearbyOrders']);
    Route::get('/driver/{driverId}/test', [ProximityController::class, 'testDriverProximity']);
    Route::post('/simulate-location', [ProximityController::class, 'simulateLocationUpdate']);
    Route::get('/stats', [ProximityController::class, 'getProximityStats']);
});

// Admin Statistics APIs
Route::prefix('admin')->group(function () {
    Route::get('/dashboard/overview', [\App\Http\Controllers\Api\Admin\StatisticsController::class, 'dashboardOverview']);
    Route::get('/statistics/revenue', [\App\Http\Controllers\Api\Admin\StatisticsController::class, 'revenueStatistics']);
    Route::get('/statistics/drivers', [\App\Http\Controllers\Api\Admin\StatisticsController::class, 'driverStatistics']);
    Route::get('/statistics/areas', [\App\Http\Controllers\Api\Admin\StatisticsController::class, 'areaStatistics']);
});
