<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Unit\Models\Messages\Ledger;

use App\Models\Messages\Ledger\EntityRef;
use App\Models\Messages\Message;
use Tests\TestCase;


class AccountRefTest extends TestCase
{
    protected array $base = [
        'code' => '1010',
        'uuid' => 'some-fake-uuid',
    ];

    public function testFromRequest()
    {
        $parentRef = EntityRef::fromRequest(
            $this->base, Message::OP_ADD | Message::F_VALIDATE
        );
        $this->assertEquals('1010', $parentRef->code);
        $this->assertEquals('some-fake-uuid', $parentRef->uuid);
    }
}
