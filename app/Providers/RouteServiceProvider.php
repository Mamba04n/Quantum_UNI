<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/inicio';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('storefront', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(30)
            ->by((string) $request->ip()));

        RateLimiter::for('finance-token', fn (Request $request) => Limit::perMinute(10)
            ->by((string) $request->ip()));

        RateLimiter::for('finance-api', function (Request $request): array {
            $token = $request->user()?->currentAccessToken();
            $device = data_get($token, 'device_id') ?: data_get($token, 'id') ?: $request->ip();
            $endpoint = $request->route()?->getName() ?: $request->path();

            return [
                Limit::perMinute(30)->by('finance:user:'.($request->user()?->id ?: $request->ip())),
                Limit::perMinute(15)->by("finance:device:{$device}:{$endpoint}"),
            ];
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
