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
        if (config('ledger.api', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $base = __DIR__ . '/../';
            $migrateFrom = $base . 'database/migrations/';

            // Export migrations
            if (!class_exists('CreatePostsTable')) {
                $this->publishes(
                    [
                        $migrateFrom . 'CreateLedgerTables.php.stub' =>
                            $this->migratePath('create_posts_table'),
                        $migrateFrom . 'AddAccountTaxCode.php.stub' =>
                            $this->migratePath('add_account_tax_code'),
                    ],
                    'migrations'
                );
            }
            $this->publishes(
                [$base . 'config/config.php' => config_path('ledger.php')],
                'config'
            );
            $this->commands([
                ImportFeatureTests::class,
                Install::class,
                Templates::class
            ]);
        }
    }

    private function migratePath(string $file): string
    {
        return database_path(
            'migrations/' . date('Y_m_d_His', time()) . "_$file.php"
        );
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

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('ledger.prefix'),
            'middleware' => config('ledger.middleware'),
        ];
    }

}
