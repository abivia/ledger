<?php

namespace Abivia\Ledger\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class Install extends Command
{
    protected $signature = 'ledger:install';

    protected $description = 'Install Abivia Ledger';

    /**
     * Artisan command to publish configuration.
     *
     * @return void
     */
    public function handle(): void
    {
        $this->info('Installing Abivia Ledger package...');

        $this->info('Publishing configuration...');

        if (!File::exists(config_path('ledger.php'))) {
            $this->publishConfiguration();
            $this->info('Published configuration');
        } elseif ($this->shouldOverwriteConfig()) {
            $this->publishConfiguration(true);
            $this->info('Overwriting configuration file...');
        } else {
            $this->info('Existing configuration was not overwritten.');
        }

        $this->info('Installed Ledger');
    }

    /**
     * Ask the user if we should overwrite the existing file.
     *
     * @return bool
     */
    private function shouldOverwriteConfig(): bool
    {
        return $this->confirm('Config file exists. Overwrite it?',false);
    }

    /**
     * Publish the configuration.
     *
     * @param bool $forcePublish When set, overwrite existing definition with default.
     *
     * @return void
     */
    private function publishConfiguration(bool $forcePublish = false): void
    {
        $params = [
            '--provider' => "Abivia\Ledger\LedgerServiceProvider",
            '--tag' => "config"
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }
}
