<?php

namespace App\Providers;

use App\Services\WbApiClient;
use App\Services\WbSyncService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Регистрируем WbApiClient
        $this->app->singleton(WbApiClient::class, function ($app) {
            $host = config('services.wb.host');
            $key = config('services.wb.key');
            
            if (empty($host) || empty($key)) {
                throw new \RuntimeException('WB API credentials are not configured. Please set WB_API_HOST and WB_API_KEY in your .env file.');
            }
            
            return new WbApiClient($host, $key);
        });

        // Регистрируем WbSyncService
        $this->app->singleton(WbSyncService::class, function ($app) {
            return new WbSyncService(
                $app->make(WbApiClient::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
