<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;


class AccountTest extends TestCase
{
    protected array $base = [
        'code' => '1010',
        'uuid' => 'some-fake-uuid',
        'names' => [
            ['name' => 'In English', 'language' => 'en'],
            ['name' => 'en francais', 'language' => 'fr'],
        ],
        'parent' => [],
        'debit' => false,
        'credit' => false,
        'extra' => 'extra-bit',
    ];

    public function testFromRequestAdd()
    {
        $base = $this->base;
        unset($base['uuid']);
        unset($base['parent']);
        $base['debit'] = true;
        $account = Account::fromArray(
            $base, Message::OP_ADD
        );
        $this->assertEquals('1010', $account->code);
        $this->assertCount(2, $account->names);
    }

    public function testFromRequestAdd_no_names()
    {
        $base = $this->base;
        unset($base['uuid']);
        unset($base['parent']);
        unset($base['names']);
        $this->expectException(Breaker::class);
        Account::fromArray($base, Message::OP_ADD | Message::F_VALIDATE);
    }

    public function testFromRequestUpdate()
    {
        $base = $this->base;
        unset($base['parent']);
        $base['revision'] = 'this-is-a-rev-code';
        $base['toCode'] = '1020';
        $account = Account::fromArray(
            $base, Message::OP_UPDATE
        );
        $this->assertEquals('1010', $account->code);
        $this->assertEquals('1020', $account->toCode);
        $this->assertCount(2, $account->names);
    }

}
