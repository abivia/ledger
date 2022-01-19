<?php

namespace Abivia\Ledger\Tests\Unit\Root;

use Abivia\Ledger\Root\Flex;
use Abivia\Ledger\Tests\TestCase;
use Illuminate\Support\Facades\App;

class FlexTest extends TestCase
{
    public function testHydration()
    {
        $obj = new Flex();

        $this->assertTrue($obj->hydrate(
            '{"rules":{"language":{"default":"en-CA"}}}'
        ));
        $this->assertEquals(
            'en-CA',
            $obj->rules->language->default
        );
    }
}
