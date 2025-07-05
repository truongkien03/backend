<?php

namespace App\Providers;

use App\Channels\FcmV1Channel;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Notification;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Đăng ký custom FCM v1 notification channel
        Notification::extend('fcm_v1', function ($app) {
            return new FcmV1Channel($app->make(\App\Services\FcmV1Service::class));
        });
    }
}
