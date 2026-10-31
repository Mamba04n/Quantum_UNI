<?php

namespace App\Providers;

use App\Domain\Administracion\Rbac\Services\UserAccessResolver;
use App\Models\PersonalAccessToken;
use App\Models\Product;
use App\Observers\ProductObserver;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Domain\Finanzas\PaymentLinks\PaymentGateway::class, \App\Domain\Finanzas\PaymentLinks\PayPalGateway::class);
        $this->app->scoped(UserAccessResolver::class, fn () => new UserAccessResolver);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min(12)->mixedCase()->numbers()->symbols();

            return config('auth.check_compromised_passwords')
                ? $rule->uncompromised()
                : $rule;
        });

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Product::observe(ProductObserver::class);

        if (! app()->runningInConsole() && request()->getBaseUrl()) {
            URL::forceRootUrl(request()->getSchemeAndHttpHost().request()->getBaseUrl());
        }

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
