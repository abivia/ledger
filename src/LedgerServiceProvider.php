<?php

namespace Abivia\Ledger;

use Abivia\Ledger\Console\Install;
use Abivia\Ledger\Console\ImportFeatureTests;
use Abivia\Ledger\Console\Templates;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    private int $migrationCount;

    public function boot()
    {
        if (config('ledger.api', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $base = __DIR__ . '/../';
            $migrateFrom = $base . 'database/migrations/';

            // Export migrations
            $this->migrationCount = 2;
            if (!class_exists('CreatePostsTable')) {
                $this->publishes(
                    [
                        $migrateFrom . 'LedgerCreateTables.php.stub' =>
                            $this->migratePath('ledger_create_tables'),
                        $migrateFrom . 'LedgerAddAccountTaxCode.php.stub' =>
                            $this->migratePath('ledger_add_account_tax_code'),
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
        $timeKludge = date('Y_m_d_His', time() - --$this->migrationCount);
        return database_path(
            'migrations/' . $timeKludge . "_$file.php"
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
