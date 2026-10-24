<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        RateLimiter::for('infrastructure', fn (Request $request) => Limit::perMinute(120)
            ->by($request->user()?->currentAccessToken()?->getKey() ?? $request->ip()));

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
