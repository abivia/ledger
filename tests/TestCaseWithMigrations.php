<?php


namespace Abivia\Ledger\Tests;

use Illuminate\Foundation\Testing\RefreshDatabaseState;

abstract class TestCaseWithMigrations extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        $this->doMigrations();
    }

    protected function doMigrations()
    {
        $base = __DIR__ . '/../database/migrations/';
        $migrations = [
            ['LedgerCreateTablesV2', true],
            // ['LedgerAddAccountTaxCode', false],
            // ['JournalEntryAddLockedFlag', false],
        ];
        foreach ($migrations as $migrationClass) {
            include_once "$base{$migrationClass[0]}.stub.php";
            $migrate = new $migrationClass[0]();
            if ($migrationClass[1]) {
                $migrate->down();
            }
            $migrate->up();
        }
        RefreshDatabaseState::$migrated = true;
    }

}
