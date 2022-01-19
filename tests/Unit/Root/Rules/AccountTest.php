<?php

namespace Abivia\Ledger\Tests\Unit\Root\Rules;

use Abivia\Ledger\Tests\TestCase;

class AccountTest extends TestCase
{
    public function testHydration()
    {
        $obj = new \Abivia\Ledger\Root\Rules\Account();
        $this->assertFalse($obj->postToCategory);
        $this->assertTrue($obj->hydrate('{"postToCategory":true}'));
        $this->assertTrue($obj->postToCategory);
    }
}
