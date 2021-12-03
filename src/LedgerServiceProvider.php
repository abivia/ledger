<?php

namespace Abivia\Ledger;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->registerRoutes();
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
