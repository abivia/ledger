<?php

namespace Abivia\Ledger\Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Abivia\Ledger\Tests\TestCase;

class ImportFeatureTestsTest extends TestCase
{
    public function testImportFeatures()
    {
        Artisan::call('ledger:_ift');

        $this->assertTrue(true);
    }

}
