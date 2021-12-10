<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Detail;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Message;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Abivia\Ledger\Tests\TestCase;
use function array_shift;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class JournalEntryQueryTest extends TestCase
{
    const TRANS_COUNT = 107;

    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'entries';
        // Create a ledger and a set of transactions.
        $this->createLedger(
            ['template', 'date'],
            ['template' => 'manufacturer', 'date' => '2001-01-01']
        );
        // Subtract one
        $this->addRandomTransactions(self::TRANS_COUNT - 1);

    }

    public function testQueryAll()
    {
        // Query for everything, unpaginated
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(107, $entries);
    }

    public function testQueryApiAll()
    {
        // Query for everything, paginated
        $pages = 0;
        $totalEntries = 0;
        $fetchData = [];
        while (1) {
            $response = $this->json(
                'post', 'api/ledger/entry/query', $fetchData
            );
            $actual = $this->isSuccessful($response);
            $entries = $actual->entries;
            ++$pages;
            $totalEntries += count($entries);
            if (count($entries) !== 25) {
                break;
            }
            $fetchData['after'] = end($entries)->id;
            $fetchData['afterDate'] = end($entries)->date;
        }
        $this->assertEquals(107, $totalEntries);
        $this->assertEquals(5, $pages);
    }

    public function testQueryDateAfter()
    {
        // Get a signature for all generated records
        $allRecs = [];
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        /** @var JournalEntry $entry */
        foreach ($controller->query($query, Message::OP_QUERY) as $entry) {
            $allRecs[$entry->journalEntryId] = $entry->transDate;
        }

        // Query by date
        $query = new EntryQuery();
        $query->date = Carbon::parse('2001-01-15');
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);

        // Make a copy of the signatures
        $remaining = $allRecs;
        foreach ($entries as $entry) {
            unset($remaining[$entry->journalEntryId]);
        }
        /** @var Carbon $notReturned */
        foreach ($remaining as $notReturned) {
            if (
                $notReturned->greaterThanOrEqualTo($query->date)
            ) {
                $this->fail();
            }
        }
    }

    public function testQueryDateBefore()
    {
        // Get a signature for all generated records
        $allRecs = [];
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        /** @var JournalEntry $entry */
        foreach ($controller->query($query, Message::OP_QUERY) as $entry) {
            $allRecs[$entry->journalEntryId] = $entry->transDate;
        }

        // Query by date
        $query = new EntryQuery();
        $query->dateEnding = Carbon::parse('2001-02-15');
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);

        // Make a copy of the signatures
        $remaining = $allRecs;
        foreach ($entries as $entry) {
            unset($remaining[$entry->journalEntryId]);
        }
        /** @var Carbon $notReturned */
        foreach ($remaining as $notReturned) {
            if (
                $notReturned->lessThanOrEqualTo($query->dateEnding)
            ) {
                $this->fail();
            }
        }
    }

    public function testQueryDateBetween()
    {
        // Get a signature for all generated records
        $allRecs = [];
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        /** @var JournalEntry $entry */
        foreach ($controller->query($query, Message::OP_QUERY) as $entry) {
            $allRecs[$entry->journalEntryId] = $entry->transDate;
        }

        // Query by date
        $query = new EntryQuery();
        $query->date = Carbon::parse('2001-01-15');
        $query->dateEnding = Carbon::parse('2001-02-15');
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);

        // Make a copy of the signatures
        $remaining = $allRecs;
        foreach ($entries as $entry) {
            unset($remaining[$entry->journalEntryId]);
        }
        /** @var Carbon $notReturned */
        foreach ($remaining as $notReturned) {
            if (
                $notReturned->greaterThanOrEqualTo($query->date)
                && $notReturned->lessThanOrEqualTo($query->dateEnding)
            ) {
                $this->fail();
            }
        }
    }

    public function testQueryPosted()
    {
        // Get a record and update it to not posted.
        $entry = JournalEntry::find(10);
        $entry->posted = false;
        $entry->save();

        // Query for everything, unpaginated
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);

        // Expect one record less than we have
        $this->assertCount(106, $entries);

        // Query again, including not posted records.
        $query->postedOnly = false;
        $entries = $controller->query($query, Message::OP_QUERY);

        // Expect the full set
        $this->assertCount(107, $entries);
    }

    public function testQueryReviewed()
    {
        // Get a record and update it to "reviewed".
        $entry = JournalEntry::find(10);
        $entry->reviewed = true;
        $entry->save();

        // Query for everything, unpaginated
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);

        // Expect all records
        $this->assertCount(107, $entries);

        // Query again, including reviewed records.
        $query->reviewed = true;
        $entries = $controller->query($query, Message::OP_QUERY);

        // Expect two records: the opening and the one we set
        $this->assertCount(2, $entries);

        // Query again, now for not reviewed records.
        $query->reviewed = false;
        $entries = $controller->query($query, Message::OP_QUERY);

        // Expect the remaining records
        $this->assertCount(105, $entries);
    }

}
