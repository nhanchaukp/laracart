<?php

namespace NhanChauKP\LaraCart\Providers;

use Illuminate\Support\ServiceProvider;
use NhanChauKP\LaraCart\Contracts\CartDriver;
use NhanChauKP\LaraCart\Drivers\DatabaseDriver;
use NhanChauKP\LaraCart\Drivers\SessionDriver;
use NhanChauKP\LaraCart\LaraCart;

/**
 * Service provider for the LaraCart package.
 *
 * This class handles the registration and bootstrapping of the LaraCart package,
 * including binding the cart driver and publishing configuration and migrations.
 */
class LaraCartServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/laracart.php', 'laracart'
        );

        $this->app->singleton(LaraCart::class, function ($app) {
            return new LaraCart($app);
        });

        $this->app->bind('laracart', function ($app) {
            return $app->make(LaraCart::class);
        });

        $this->app->bind(CartDriver::class, function ($app) {
            $driver = config('laracart.driver', 'database');

            return match ($driver) {
                'session' => new SessionDriver($app),
                default => new DatabaseDriver($app),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/laracart.php' => config_path('laracart.php'),
        ], 'laracart-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'laracart-migrations');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
