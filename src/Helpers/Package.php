<?php

namespace Abivia\Ledger\Helpers;

class Package
{
    static function chartPath(string $path = ''): string
    {
        return \realpath(
            config('ledger.chartPath', __DIR__ . '/../../resources/ledger/charts')
            .($path ? DIRECTORY_SEPARATOR . $path : $path)
        );

    }
}
