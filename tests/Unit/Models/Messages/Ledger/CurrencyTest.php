<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Unit\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\Messages\Ledger\Currency;
use App\Models\Messages\Message;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    protected array $base = [
        'code' => 'CAD',
        'decimals' => 2,
    ];

    public function testFromRequest()
    {
        $parentRef = Currency::fromRequest(
            $this->base, Message::OP_ADD | Message::OP_VALIDATE
        );
        $this->assertEquals('CAD', $parentRef->code);
        $this->assertEquals(2, $parentRef->decimals);
    }

    public function testFromRequest_no_decimals()
    {
        $bad = $this->base;
        unset($bad['decimals']);
        $this->expectException(Breaker::class);
        Currency::fromRequest($bad, Message::OP_ADD | Message::OP_VALIDATE);
    }

    public function testFromRequest_no_code()
    {
        $bad = $this->base;
        unset($bad['code']);
        $this->expectException(Breaker::class);
        Currency::fromRequest($bad, Message::OP_ADD | Message::OP_VALIDATE);
    }

}
