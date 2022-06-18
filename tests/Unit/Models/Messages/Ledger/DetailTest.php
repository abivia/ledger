<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Detail;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;


class DetailTest extends TestCase {

    public function testNormalizeAmount()
    {
        $detail = new Detail();
        $detail->amount = '3.0';
        $detail->normalizeAmount();
        $this->assertEquals('3.', $detail->amount);
        $this->assertFalse(isset($detail->debit));
        $this->assertFalse(isset($detail->credit));

        $detail->normalizeAmount(2);
        $this->assertEquals('3.00', $detail->amount);
    }

    public function testNormalizeAmountBad()
    {
        $detail = new Detail();
        $detail->amount = '3-0';

        $this->expectException(Breaker::class);
        $detail->normalizeAmount();
    }

    public function testNormalizeAmountCredit()
    {
        $detail = new Detail();
        $detail->credit = '3.0';
        $detail->normalizeAmount();
        $this->assertEquals('3.', $detail->amount);
        $this->assertFalse(isset($detail->debit));
        $this->assertFalse(isset($detail->credit));

        $detail->normalizeAmount(2);
        $this->assertEquals('3.00', $detail->amount);
    }

    public function testNormalizeAmountCreditBad()
    {
        $detail = new Detail();
        $detail->credit = '3-0';

        $this->expectException(Breaker::class);
        $detail->normalizeAmount();
    }

    public function testNormalizeAmountDebit()
    {
        $detail = new Detail();
        $detail->debit = '3.0';
        $detail->normalizeAmount();
        $this->assertEquals('-3.', $detail->amount);
        $this->assertFalse(isset($detail->debit));
        $this->assertFalse(isset($detail->credit));

        $detail->normalizeAmount(2);
        $this->assertEquals('-3.00', $detail->amount);
    }

    public function testNormalizeAmountDebitBad()
    {
        $detail = new Detail();
        $detail->debit = '3-0';

        $this->expectException(Breaker::class);
        $detail->normalizeAmount();
    }

}
