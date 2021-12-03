<?php

namespace Abivia\Ledger\Tests;

use Abivia\Ledger\LedgerServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected $loadEnvironmentVariables = true;

    protected function getEnvironmentSetUp($app)
    {
        // perform environment setup
    }

    protected function getPackageProviders($app)
    {
        return [
            LedgerServiceProvider::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }
}
