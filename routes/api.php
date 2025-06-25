<?php

use App\Http\Controllers\Api\Auth\LoginWithOtpController;
use App\Http\Controllers\Api\Auth\ProfileController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\CurrentLocationController;
use App\Http\Controllers\Api\Driver\Auth\LoginController;
use App\Http\Controllers\Api\Driver\Auth\RegisterController as DriverRegisterController;
use App\Http\Controllers\Api\Driver\Auth\SendOtpController as DriverSendOtpController;
use App\Http\Controllers\Api\Driver\OrderController as AppOrderController;
use App\Http\Controllers\Api\Driver\ProfileController as DriverProfileController;
use App\Http\Controllers\Api\Driver\SharingController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

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
Route::post('/login', [LoginWithOtpController::class, 'login']);
Route::post('/login/otp', [LoginWithOtpController::class, 'sendOtp']);
Route::post('/password/reset', [ProfileController::class, 'resetPassword']);
Route::post('/password/forgot', [ProfileController::class, 'forgotPassword']);

Route::middleware(['auth:api'])->group(function () {
    Route::post('/fcm/token', [DriverProfileController::class, 'addFcmToken']);
    Route::delete('/fcm/token', [DriverProfileController::class, 'removeFcmToken']);
    Route::get('/notifications', [ProfileController::class, 'notifications']);
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::post('/password', [ProfileController::class, 'changePassword']);
    Route::post('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/avatar', [ProfileController::class, 'changeAvatar']);
    Route::get('/orders/inproccess', [OrderController::class, 'getInProcessList']);
    Route::get('/orders/completed', [OrderController::class, 'getCompleteList']);
    Route::get('/shipping-fee', [OrderController::class, 'getShippingFee']);
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
    Route::post('/fcm/token', [DriverProfileController::class, 'addFcmToken']);
    Route::delete('/fcm/token', [DriverProfileController::class, 'removeFcmToken']);
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

        Route::middleware(['profileVerified'])->group(function () {
            Route::post('current-location', [CurrentLocationController::class, 'updateLocation']);
            Route::get('/orders/summary', [AppOrderController::class, 'summary']);
            Route::post('/orders/{order}/accept', [AppOrderController::class, 'acceptOrder']);
            Route::post('/orders/{order}/decline', [AppOrderController::class, 'declineOrder']);
            Route::post('/orders/{order}/complete', [AppOrderController::class, 'conpleteOrder']);
            Route::get('/orders/{order}', [AppOrderController::class, 'detail']);
            Route::post('/orders/{order}/drivers/sharing', [AppOrderController::class, 'orderSharing']);
            Route::post('/orders/{order}/drivers/sharing/accept', [AppOrderController::class, 'acceptOrderSharing']);
            Route::post('/orders/{order}/drivers/sharing/decline', [AppOrderController::class, 'declineOrderSharing']);
        });
    });
});
