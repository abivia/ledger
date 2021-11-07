<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Unit\Models\Messages\Ledger;

use App\Models\Messages\Ledger\Domain;
use App\Models\Messages\Message;
use Tests\TestCase;


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
        $domain = Domain::fromRequest(
            $this->base, Message::OP_ADD | Message::OP_VALIDATE
        );
        $this->assertEquals('GL', $domain->code);
        $this->assertCount(2, $domain->names);
        $this->assertEquals('CAD', $domain->currencyDefault);
    }
}
