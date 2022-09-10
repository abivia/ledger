<?php

namespace Abivia\Ledger;

use Abivia\Ledger\Console\ImportFeatureTests;
use Abivia\Ledger\Console\Install;
use Abivia\Ledger\Console\Templates;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class LedgerServiceProvider extends ServiceProvider
{
    private static array $branches = [
        'LedgerCreateTables' => [
            'LedgerCreateTables',
            'LedgerAddAccountTaxCode',
            'JournalEntryAddLockedFlag',
            'JournalEntryAddClearingFlag',
        ],
        'LedgerCreateTablesV2' => [
            'LedgerCreateTablesV2',
        ],
    ];
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
            $this->migrationCount = count(self::$branches);

            $published = $this->getExistingMigrations();
            $publishes = [];

            $branch = $this->getWorkingBranch($published);
            foreach (self::$branches[$branch] as $migrationClass) {
                $migrationFile = Str::snake($migrationClass);
                if (!isset($published[$migrationFile])) {
                    $publishes[$migrateFrom . $migrationClass . '.stub.php'] =
                        $this->migratePath($migrationFile);
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

    private function getWorkingBranch(array $published): string
    {
        foreach (self::$branches as $branch => $migrations) {
            $baseFile = Str::snake($branch);
            if (isset($published[$baseFile])) {
                return $branch;
            }
        }

        return $branch;
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
