<?php

declare(strict_types=1);

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
     */
    final public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    #[\Override]
    public function boot(): void
    {
        RateLimiter::for(
            'api',
            fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip())
        );

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group($this->app->basePath('routes/api.php'));

            Route::middleware('web')
                ->group($this->app->basePath('routes/web.php'));
        });
    }
}
