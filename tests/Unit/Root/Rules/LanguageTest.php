<?php

namespace Abivia\Ledger\Tests\Unit\Root\Rules;

use Abivia\Ledger\Root\Rules\Language;
use Abivia\Ledger\Tests\TestCase;
use Illuminate\Support\Facades\App;

class LanguageTest extends TestCase
{
    public function testHydration()
    {
        $obj = new Language();
        $this->assertEquals(App::getLocale(), $obj->default);
        $this->assertTrue($obj->hydrate('{"default":"en-CA"}'));
        $this->assertEquals('en-CA', $obj->default);
    }
}
