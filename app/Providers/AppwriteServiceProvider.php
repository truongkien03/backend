<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Appwrite\Client;
use Appwrite\Services\Databases;
use Appwrite\Services\Storage;
use Appwrite\Services\Functions;
use Appwrite\Services\Realtime;

class AppwriteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $client = new Client();
            
            $client
                ->setEndpoint(config('appwrite.endpoint'))
                ->setProject(config('appwrite.project_id'))
                ->setKey(config('appwrite.api_key'));

            return $client;
        });

        $this->app->singleton(Databases::class, function ($app) {
            return new Databases($app->make(Client::class));
        });

        $this->app->singleton(Storage::class, function ($app) {
            return new Storage($app->make(Client::class));
        });

        $this->app->singleton(Functions::class, function ($app) {
            return new Functions($app->make(Client::class));
        });

        $this->app->singleton(Realtime::class, function ($app) {
            return new Realtime($app->make(Client::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 