<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);

        $middleware->trustHosts(
            at: fn (): array => config('trustedproxy.hosts', []),
            subdomains: false,
        );

        // Only trusts proxies explicitly listed via TRUSTED_PROXIES; stays a
        // no-op (no proxies trusted) until that env var is set, so this is
        // safe to enable ahead of ever sitting behind a reverse proxy/CDN.
        // Reads env() directly (not config()) because the config repository
        // isn't bound to the container yet at this point in the bootstrap.
        $trustedProxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES')),
        )));

        $middleware->trustProxies(
            at: $trustedProxies === [] ? null : $trustedProxies,
        );

        $middleware->alias([
            'permission' => CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->respond(function (SymfonyResponse $response, Throwable $exception, Request $request) {
            if ($response->getStatusCode() !== 419) {
                return $response;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('status', 'Sesi login kedaluwarsa. Silakan coba login lagi.');
        });
    })->create();
