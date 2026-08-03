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

        RateLimiter::for('report-export', function (Request $request): Limit {
            $userId = $request->user()?->getAuthIdentifier();
            $key = $userId !== null
                ? "report-export:user:{$userId}"
                : "report-export:ip:{$request->ip()}";

            return Limit::perMinute(max(1, (int) config('reports.export_rate_limit_per_minute', 10)))
                ->by($key)
                ->response(fn () => response()->json([
                    'message' => 'Terlalu banyak permintaan export laporan. Silakan coba lagi nanti.',
                ], 429));
        });
    }
}
