<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Unit\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\Messages\Ledger\Account;
use App\Models\Messages\Message;
use Tests\TestCase;


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
        $account = Account::fromRequest(
            $base, Message::OP_ADD | Message::OP_VALIDATE
        );
        $this->assertEquals('1010', $account->code);
        $this->assertCount(2, $account->names);
    }

    public function testFromRequestAdd_no_code()
    {
        $base = $this->base;
        unset($base['code']);
        unset($base['uuid']);
        unset($base['parent']);
        $this->expectException(Breaker::class);
        Account::fromRequest($base, Message::OP_ADD | Message::OP_VALIDATE);
    }

    public function testFromRequestAdd_by_uuid()
    {
        $base = $this->base;
        unset($base['code']);
        unset($base['parent']);
        $this->expectException(Breaker::class);
        Account::fromRequest($base, Message::OP_ADD | Message::OP_VALIDATE);
    }

    public function testFromRequestAdd_no_names()
    {
        $base = $this->base;
        unset($base['uuid']);
        unset($base['parent']);
        unset($base['names']);
        $this->expectException(Breaker::class);
        Account::fromRequest($base, Message::OP_ADD | Message::OP_VALIDATE);
    }

    public function testFromRequestUpdate()
    {
        $base = $this->base;
        unset($base['parent']);
        $base['revision'] = 'this-is-a-rev-code';
        $base['toCode'] = '1020';
        $account = Account::fromRequest(
            $base, Message::OP_UPDATE | Message::OP_VALIDATE
        );
        $this->assertEquals('1010', $account->code);
        $this->assertEquals('1020', $account->toCode);
        $this->assertCount(2, $account->names);
    }

}
