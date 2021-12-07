<?php

namespace Abivia\Ledger\Helpers;

use function realpath;

/**
 * Support for accessing in-package resources.
 */
class Package
{
    /**
     * Get the path to the chart of accounts.
     *
     * @param string $path Sub-path or file to append to the chart path.
     * @return string
     */
    static function chartPath(string $path = ''): string
    {
        return realpath(
            config('ledger.chartPath', __DIR__ . '/../../resources/ledger/charts')
            .($path ? DIRECTORY_SEPARATOR . $path : $path)
        );

    }
}
