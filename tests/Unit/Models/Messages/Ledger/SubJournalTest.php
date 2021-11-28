<?php
/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Unit\Models\Messages\Ledger;

use App\Models\Messages\Ledger\SubJournal;
use App\Models\Messages\Message;
use Tests\TestCase;


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
        $subJournal = SubJournal::fromRequest(
            $this->base, Message::OP_ADD | Message::F_VALIDATE
        );
        $this->assertEquals('Corp', $subJournal->code);
        $this->assertCount(2, $subJournal->names);
    }
}
