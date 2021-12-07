<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Messages\Ledger\EntityRef;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;


class AccountRefTest extends TestCase
{
    protected array $base = [
        'code' => '1010',
        'uuid' => 'some-fake-uuid',
    ];

    public function testFromRequest()
    {
        $parentRef = EntityRef::fromRequest(
            $this->base, Message::OP_ADD
        );
        $this->assertEquals('1010', $parentRef->code);
        $this->assertEquals('some-fake-uuid', $parentRef->uuid);
    }
}
