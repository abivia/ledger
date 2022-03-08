<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;

class CurrencyTest extends TestCase
{
    protected array $base = [
        'code' => 'CAD',
        'decimals' => 2,
    ];

    public function testConstruct()
    {
        $obj = new Currency('cad', 2);
        $obj->validate();
        $this->assertTrue(true);
    }

    public function testConstructBad()
    {
        $obj = new Currency('cad');
        $this->expectException(Breaker::class);
        $obj->validate();
    }

    public function testFromRequest()
    {
        $parentRef = Currency::fromArray(
            $this->base, Message::OP_ADD | Message::F_VALIDATE
        );
        $this->assertEquals('CAD', $parentRef->code);
        $this->assertEquals(2, $parentRef->decimals);
    }

    public function testFromRequest_no_decimals()
    {
        $bad = $this->base;
        unset($bad['decimals']);
        $this->expectException(Breaker::class);
        Currency::fromArray($bad, Message::OP_ADD | Message::F_VALIDATE);
    }

    public function testFromRequest_no_code()
    {
        $bad = $this->base;
        unset($bad['code']);
        $this->expectException(Breaker::class);
        Currency::fromArray($bad, Message::OP_ADD | Message::F_VALIDATE);
    }

}
