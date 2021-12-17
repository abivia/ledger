<?php

namespace Abivia\Ledger;

use Abivia\Ledger\Console\Install;
use Abivia\Ledger\Console\ImportFeatureTests;
use Abivia\Ledger\Console\Templates;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        if (config('ledger.api', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [__DIR__ . '/../config/config.php' => config_path('ledger.php')],
                'config'
            );
            $this->commands([
                ImportFeatureTests::class,
                Install::class,
                Templates::class
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'ledger');
    }

    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        });
    }

    protected function routeConfiguration()
    {
        return [
            'prefix' => config('ledger.prefix'),
            'middleware' => config('ledger.middleware'),
        ];
    }

}
