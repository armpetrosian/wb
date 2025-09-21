<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\TokenService;

class TokenServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TokenService::class, function ($app) {
            return new TokenService();
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
