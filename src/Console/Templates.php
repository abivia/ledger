<?php

namespace Abivia\Ledger\Console;

use Abivia\Ledger\Helpers\Package;
use Illuminate\Console\Command;
use function is_file;
use function scandir;

class Templates extends Command
{
    protected $signature = 'ledger:templates';

    protected $description = 'List Chart of Account Templates';

    /**
     * Artisan command to list available chart of account templates
     *
     * @return void
     */
    public function handle(): void
    {

        $this->info('Available Charts of Account:');

        $path = Package::chartPath();
        $files = scandir($path);
        $path .= '/';
        foreach ($files as $file) {
            $parts = pathinfo($file);
            if (is_file($path . $file) && $parts['extension'] === 'json') {
                $this->info('    ' . $parts['filename']);
            }
        }
    }

}
