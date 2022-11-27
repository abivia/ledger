<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;


class DomainTest extends TestCase
{
    protected array $base = [
        'code' => 'GL',
        'names' => [
            ['name' => 'In English', 'language' => 'en'],
            ['name' => 'en francais', 'language' => 'fr'],
        ],
        'currency' => 'CAD',
    ];

    public function testFromRequestAdd()
    {
        $domain = Domain::fromArray(
            $this->base, Message::OP_ADD
        );
        $this->assertEquals('GL', $domain->code);
        $this->assertCount(2, $domain->names);
        $this->assertEquals('CAD', $domain->currencyDefault);
    }

    public function testFromRequestAdd_bad()
    {
        $base = $this->base;
        unset($base['names']);
        $this->expectException(Breaker::class);
        Domain::fromArray(
            $base, Message::OP_ADD | Message::F_VALIDATE
        );
    }

    public function testFromRequestDelete()
    {
        $base = $this->base;
        unset($base['names']);
        $base['revision'] = 'revision-code';
        $domain = Domain::fromArray(
            $base, Message::OP_DELETE | Message::F_VALIDATE
        );
        $this->assertEquals('GL', $domain->code);
        $this->assertEquals('CAD', $domain->currencyDefault);
    }

    public function testFromRequestDelete_bad()
    {
        $base = $this->base;
        unset($base['names']);
        $domain = Domain::fromArray(
            $base, Message::OP_DELETE | Message::F_VALIDATE
        );
        $this->assertEquals('GL', $domain->code);
        $this->assertEquals('CAD', $domain->currencyDefault);
    }

    public function testFromRequestGet()
    {
        $domain = Domain::fromArray(
            $this->base, Message::OP_ADD
        );
        $this->assertEquals('GL', $domain->code);
        $this->assertCount(2, $domain->names);
        $this->assertEquals('CAD', $domain->currencyDefault);
    }

    public function testFromRequestUpdate()
    {
        $domain = Domain::fromArray(
            $this->base, Message::OP_ADD
        );
        $this->assertEquals('GL', $domain->code);
        $this->assertCount(2, $domain->names);
        $this->assertEquals('CAD', $domain->currencyDefault);
    }

}
