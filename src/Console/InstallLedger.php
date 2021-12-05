<?php

namespace Abivia\Ledger\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallLedger extends Command
{
    protected $signature = 'ledger:install';

    protected $description = 'Install Abivia Ledger';

    public function handle()
    {
        $this->info('Installing Abivia Ledger package...');

        $this->info('Publishing configuration...');

        if (! $this->configExists('ledger.php')) {
            $this->publishConfiguration();
            $this->info('Published configuration');
        } else {
            if ($this->shouldOverwriteConfig()) {
                $this->info('Overwriting configuration file...');
                $this->publishConfiguration(true);
            } else {
                $this->info('Existing configuration was not overwritten.');
            }
        }

        $this->info('Installed Ledger');
    }

    private function configExists($fileName): bool
    {
        return File::exists(config_path($fileName));
    }

    private function shouldOverwriteConfig()
    {
        return $this->confirm(
            'Config file exists. Overwrite it?',
            false
        );
    }

    private function publishConfiguration($forcePublish = false)
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
