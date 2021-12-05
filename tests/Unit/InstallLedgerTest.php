<?php

namespace Abivia\Ledger\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Abivia\Ledger\Tests\TestCase;

class InstallLedgerTest extends TestCase
{
    private function cleanUp()
    {
        if (File::exists(config_path('ledger.php'))) {
            unlink(config_path('ledger.php'));
        }
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->cleanUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->cleanUp();
    }

    public function testConfigInstalled()
    {
        $this->assertFalse(File::exists(config_path('ledger.php')));

        Artisan::call('ledger:install');

        $temp = config_path('ledger.php');
        $this->assertTrue(File::exists(config_path('ledger.php')));
    }

    /** @test */
    public function testConfigIsOverwritten()
    {
        // Fake an existing config file
        File::put(config_path('ledger.php'), 'test contents');
        $this->assertTrue(File::exists(config_path('ledger.php')));

        // Run the installer
        $command = $this->artisan('ledger:install');

        // Expect a warning that our configuration file exists
        $command->expectsConfirmation(
            'Config file exists. Overwrite it?',
            // When answered with "yes"
            'yes'
        );

        $command->execute();

        $command->expectsOutput('Overwriting configuration file...');

        // Assert that the original contents are overwritten
        $this->assertEquals(
            file_get_contents(__DIR__ . '/../../config/config.php'),
            file_get_contents(config_path('ledger.php'))
        );
    }

    public function testConfigNotOverwritten()
    {
        // Fake an existing config file
        File::put(config_path('ledger.php'), 'test contents');
        $this->assertTrue(File::exists(config_path('ledger.php')));

        // Run the installer
        $command = $this->artisan('ledger:install');

        // Expect a warning that our configuration file exists
        $command->expectsConfirmation(
            'Config file exists. Overwrite it?',
            // When answered with "no"
            'no'
        );

        // We should see a message that our file was not overwritten
        $command->expectsOutput('Existing configuration was not overwritten.');

        // Assert that the original contents of the config file remain
        $this->assertEquals('test contents', file_get_contents(config_path('ledger.php')));
    }
}
