<?php


namespace Abivia\Ledger\Tests;

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
            'CreateLedgerTables',
            'AddAccountTaxCode'
        ];
        foreach ($migrations as $migrationClass) {
            include_once "$base$migrationClass.php.stub";
            (new $migrationClass)->up();
        }
    }

}
