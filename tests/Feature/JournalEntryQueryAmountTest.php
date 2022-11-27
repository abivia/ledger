<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Messages\Detail;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;


/**
 * Test entry queries incorporating a Journal Reference
 */
class JournalEntryQueryAmountTest extends TestCaseWithMigrations
{
    const TRANS_COUNT = 30;

    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;
    use ValidatesJson;

    private array $references = [];
    private array $referenceUses = [];

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
        // Subtract one for the opening balances.
        $this->addRandomTransactions(self::TRANS_COUNT - 1);

    }

    /**
     * @throws Exception
     */
    protected function addRandomTransactions(int $count) {
        // Get a list of accounts in the ledger
        $codes = $this->getPostingAccounts();

        $forDate = new Carbon('2001-01-02');
        $transId = 0;
        $amount = 0.0;
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
                if ($transId & 1) {
                    $amount += 100.0;
                }

                // First detail
                $entry->details[] = new Detail(
                    new EntityRef(array_pop($shuffled)),
                    (string)($amount / 100)
                );

                // Second detail
                $entry->details[] = new Detail(
                    new EntityRef(array_pop($shuffled)),
                    (string)(-$amount / 100)
                );

                $controller->add($entry);
            }
        } catch (Breaker $exception) {
            echo $exception->getMessage() . "\n"
                . implode("\n", $exception->getErrors());
        }
    }

    public function testQueryAmountBetween()
    {
        $query = new EntryQuery();
        $query->amount = '100.60';
        $query->amountMax = '120';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(0, $entries->count());

        $query->amount = '2.10';
        $query->amountMax = '2.20';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(0, $entries->count());

        $query->amount = '2.00';
        $query->amountMax = '6.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(10, $entries->count());

        $query->amount = '8.00';
        $query->amountMax = '11';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(8, $entries->count());

        $query->amount = '-2.00';
        $query->amountMax = '-6.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(10, $entries->count());

        $query->amount = '2.00';
        $query->amountMax = '-6.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(10, $entries->count());
    }

    public function testQueryAmountEqual()
    {
        $query = new EntryQuery();
        $query->amount = '27.60';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(0, $entries->count());

        $query->amount = '2.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(2, $entries->count());

        $query->amount = '2.001';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(2, $entries->count());

        $query->amount = '-2.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertEquals(2, $entries->count());
    }

    public function testQueryApiAmountBetween()
    {
        // Query for everything, paginated
        $fetchData = [];
        $fetchData['amount'] = ['100.60', '120'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->validateResponse($actual, 'entryquery-response');
        $this->assertCount(0, $actual->entries);

        $fetchData['amount'] = ['2.10', '2.20'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(0, $actual->entries);

        $fetchData['amount'] = ['2.00', '6'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->validateResponse($actual, 'entryquery-response');
        $this->assertCount(10, $actual->entries);

        $fetchData['amount'] = ['8.00', '11.00'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(8, $actual->entries);

        $fetchData['amount'] = ['-2', '-6'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(10, $actual->entries);


        $fetchData['amount'] = ['2', '-6'];
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(10, $actual->entries);
    }

    public function testQueryApiAmountEqual()
    {
        // Query for everything, paginated
        $fetchData = [];
        $fetchData['amount'] = '27.60';
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(0, $actual->entries);

        $fetchData['amount'] = '2.00';
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->entries);

        $fetchData['amount'] = '2.001';
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->entries);

        $fetchData['amount'] = '-2.0';
        $response = $this->json(
            'post', 'api/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->entries);

    }

}
