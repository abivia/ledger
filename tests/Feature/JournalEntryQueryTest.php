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
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Abivia\Ledger\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use function array_shift;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class JournalEntryQueryTest extends TestCaseWithMigrations
{
    const TRANS_COUNT = 107;

    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;
    use ValidatesJson;

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'entries';
        // Create a ledger and a set of transactions.
        $this->createLedger(
            ['template', 'date'],
            ['template' => 'manufacturer_1.0', 'date' => '2001-01-01']
        );
        // Subtract one
        $this->addRandomTransactions(self::TRANS_COUNT);

    }

    public function testQueryAll()
    {
        // Query for everything, unpaginated
        $query = new EntryQuery();
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(107, $entries);
        // Crude table export for testing.
        //$this->exportSnapshot(__DIR__ . '/../Seeders/tqa.sql');
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
            // Check the response against our schema
            $this->validateResponse($actual, 'entryquery-response');
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

    public function testQueryDomainBad()
    {
        // Query for everything, unpaginated
        $query = new EntryQuery();
        $query->domain = new EntityRef();
        $query->domain->code = 'fubar';
        $controller = new JournalEntryController();
        $this->expectException(Breaker::class);
        $controller->query($query, Message::OP_QUERY);
    }

    public function testQueryDomainApiBad()
    {
        // Query for everything, paginated
        $pages = 0;
        $totalEntries = 0;
        $fetchData = ['domain' => 'fubar'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isFailure($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'entryquery-response');
    }

    public function testQueryReviewed()
    {
        // Get a record and update it to "reviewed".
        $entry = JournalEntry::skip(10)->first();
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

        // Expect one record
        $this->assertCount(1, $entries);

        // Query again, now for not reviewed records.
        $query->reviewed = false;
        $entries = $controller->query($query, Message::OP_QUERY);

        // Expect the remaining records
        $this->assertCount(106, $entries);
    }

}
