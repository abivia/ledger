<?php

namespace Abivia\Ledger\Tests\Unit\Root\Rules;

use Abivia\Ledger\Root\Rules\Entry;
use Abivia\Ledger\Tests\TestCase;

class EntryTest extends TestCase
{
    public function testHydration()
    {
        $obj = new Entry();
        $this->assertFalse($obj->reviewed);
        $this->assertTrue($obj->hydrate('{"reviewed":true}'));
        $this->assertTrue($obj->reviewed);
    }
}
