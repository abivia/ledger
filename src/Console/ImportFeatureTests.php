<?php

namespace Abivia\Ledger\Console;

use Abivia\Ledger\Helpers\Package;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use function scandir;

class ImportFeatureTests extends Command
{
    protected $description = 'Import Feature Tests';
    protected $hidden = true;
    protected $signature = 'ledger:_ift';

    protected string $target;

    protected bool $testing;

    private function checkDir(string $path)
    {
        if (!is_dir($path)) {
            mkdir($path, 0744, true);
        }
    }

    /**
     * Artisan command to import feature tests into the app.
     *
     * @return void
     */
    public function handle(): void
    {

        $this->info('Importing');

        foreach (['Feature', 'Seeders'] as $folder) {
            $this->target = app_path("../tests/$folder");
            $this->testing = strpos($this->target, 'vendor') !== false;
            $path = realpath(__DIR__ . "/../../tests/$folder");
            $this->port($path, '');
        }
    }

    private function port(string $path, string $relativeBase)
    {
        $files = scandir($path);
        $path .= '/';
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $parts = pathinfo($file);
            $filePath = $path . $file;
            $relativePath = $relativeBase . '/' . $file;
            if (is_dir($filePath)) {
                $this->port($filePath, $relativePath);
            } elseif (is_file($filePath)) {
                $target = $this->target . $relativePath;
                if ($parts['extension'] === 'php') {
                    $code = $this->transform($filePath);
                    if ($this->testing) {
                        $this->info("Transformed $relativePath");
                    } else {
                        $this->checkDir($this->target . $relativeBase);
                        file_put_contents($target, $code);
                    }
                } elseif ($parts['extension'] === 'sql') {
                    if ($this->testing) {
                        $this->info("Copies $relativePath");
                    } else {
                        $this->checkDir($this->target . $relativeBase);
                        @unlink($target);
                        copy($filePath, $target);
                    }
                }
            }
        }
    }

    private function transform(string $filePath)
    {
        $code = file_get_contents($filePath);
        $code = str_replace(
            ['Abivia\Ledger\Tests\\'],
            ['Tests\\'],
            $code
        );
        return $code;
    }

}
