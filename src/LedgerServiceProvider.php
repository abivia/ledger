<?php

namespace Abivia\Ledger;

use Abivia\Ledger\Console\Install;
use Abivia\Ledger\Console\ImportFeatureTests;
use Abivia\Ledger\Console\Templates;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class LedgerServiceProvider extends ServiceProvider
{
    private int $migrationCount;
    private static array $migrations = [
        'LedgerCreateTables',
        'LedgerAddAccountTaxCode',
        'JournalEntryAddLockedFlag',
    ];

    public function boot()
    {
        if (config('ledger.api', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $base = __DIR__ . '/../';
            $migrateFrom = $base . 'database/migrations/';

            // Export migrations
            $this->migrationCount = count(self::$migrations);

            $published = $this->getExistingMigrations();
            $publishes = [];
            foreach (self::$migrations as $migrationClass) {
                $baseFile = Str::snake($migrationClass);
                if (!isset($published[$baseFile])) {
                    $publishes[$migrateFrom . $migrationClass . '.stub.php'] =
                        $this->migratePath($baseFile);
                }
            }
            $this->publishes($publishes, 'migrations');

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

    private function getExistingMigrations(): array
    {
        $migrations = [];
        foreach (scandir(database_path('migrations/')) as $file) {
            if (preg_match(
                '![0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_(.*?)\.php!',
                $file, $matches
            )) {
                $migrations[$matches[1]] = true;
            }
        }

        return $migrations;
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
