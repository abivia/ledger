<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Abivia\Ledger\Tests\Unit\Models\Messages\Ledger;

use Abivia\Ledger\Messages\SubJournal;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Tests\TestCase;


class SubJournalTest extends TestCase
{
    protected array $base = [
        'code' => 'Corp',
        'names' => [
            ['name' => 'In English', 'language' => 'en'],
            ['name' => 'en francais', 'language' => 'fr'],
        ],
        'currency' => 'CAD',
    ];

    public function testFromRequestAdd()
    {
        $subJournal = SubJournal::fromArray(
            $this->base, Message::OP_ADD
        );
        $this->assertEquals('Corp', $subJournal->code);
        $this->assertCount(2, $subJournal->names);
    }
}
