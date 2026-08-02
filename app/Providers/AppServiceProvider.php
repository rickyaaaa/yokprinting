<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('invoice-pdf', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();
            $key = $userId !== null
                ? "user:{$userId}"
                : "ip:{$request->ip()}";

            return Limit::perMinute(10)->by($key);
        });
    }
}
