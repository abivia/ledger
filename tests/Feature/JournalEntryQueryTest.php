<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Ledger\Detail;
use Abivia\Ledger\Messages\Ledger\EntityRef;
use Abivia\Ledger\Messages\Ledger\Entry;
use Abivia\Ledger\Messages\Ledger\EntryQuery;
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
            ['template' => 'common', 'date' => '2001-01-01']
        );
        // Subtract one
        $this->addRandomTransactions(self::TRANS_COUNT - 1);

    }

    /**
     * @throws Exception
     */
    protected function addRandomTransactions(int $count) {
        // Get a list of accounts in the ledger
        $codes = [];
        foreach (LedgerAccount::all() as $account) {
            $codes[] = $account->code;
        }
        // Get rid of the root
        array_shift($codes);
        $forDate = new Carbon('2001-01-02');
        $transId = 0;
        $shuffled = [];
        shuffle($shuffled);
        $controller = new JournalEntryController();
        try {
            while ($transId++ < $count) {
                if (count($shuffled) < 2) {
                    $shuffled = $codes;
                    shuffle($shuffled);
                }
                $entry = new Entry();
                $entry->currency = 'CAD';
                $entry->description = "Random entry $transId";
                $entry->transDate = clone $forDate;
                $entry->transDate->addDays(random_int(0, $count));
                $amount = (float)random_int(-99999, 99999);
                $entry->details = [
                    new Detail(
                        new EntityRef(array_pop($shuffled)),
                        (string)($amount / 100)
                    ),
                    new Detail(
                        new EntityRef(array_pop($shuffled)),
                        (string)(-$amount / 100)
                    ),
                ];
                $controller->add($entry);
            }
        } catch (Breaker $exception) {
            echo $exception->getMessage() . "\n"
                . implode("\n", $exception->getErrors());
        }
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

}
