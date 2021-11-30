<?php /** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Exceptions\Breaker;
use App\Http\Controllers\JournalEntryController;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\JournalReference;
use App\Models\LedgerAccount;
use App\Models\LedgerBalance;
use App\Models\LedgerDomain;
use App\Models\Messages\Ledger\Detail;
use App\Models\Messages\Ledger\EntityRef;
use App\Models\Messages\Ledger\Entry;
use App\Models\Messages\Ledger\EntryQuery;
use App\Models\Messages\Ledger\Reference;
use App\Models\Messages\Message;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use function array_shift;

/**
 * Test entry queries incorporating a Journal Reference
 */
class JournalEntryQueryAmountTest extends TestCase
{
    const TRANS_COUNT = 30;

    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    private array $references = [];
    private array $referenceUses = [];

    public function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );
        self::$expectContent = 'entries';
        // Create a ledger and a set of transactions.
        $this->createLedger(
            ['template', 'date'],
            ['template' => 'common', 'date' => '2001-01-01']
        );
        // Subtract one for the opening balances.
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
        $this->assertCount(0, $entries);

        $query->amount = '2.10';
        $query->amountMax = '2.20';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(0, $entries);

        $query->amount = '2.00';
        $query->amountMax = '6.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(10, $entries);

        $query->amount = '8.00';
        $query->amountMax = '11';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(8, $entries);

        $query->amount = '-2.00';
        $query->amountMax = '-6.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(10, $entries);

        $query->amount = '2.00';
        $query->amountMax = '-6.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(10, $entries);
    }

    public function testQueryAmountEqual()
    {
        $query = new EntryQuery();
        $query->amount = '27.60';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(0, $entries);

        $query->amount = '2.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(2, $entries);

        $query->amount = '2.001';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(2, $entries);

        $query->amount = '-2.00';
        $controller = new JournalEntryController();
        $entries = $controller->query($query, Message::OP_QUERY);
        $this->assertCount(2, $entries);
    }

    public function testQueryApiAmountBetween()
    {
        // Query for everything, paginated
        $fetchData = [];
        $fetchData['amount'] = ['100.60', '120'];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(0, $actual->entries);

        $fetchData['amount'] = ['2.10', '2.20'];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(0, $actual->entries);

        $fetchData['amount'] = ['2.00', '6'];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(10, $actual->entries);

        $fetchData['amount'] = ['8.00', '11.00'];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(8, $actual->entries);

        $fetchData['amount'] = ['-2', '-6'];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(10, $actual->entries);


        $fetchData['amount'] = ['2', '-6'];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
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
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(0, $actual->entries);

        $fetchData['amount'] = '2.00';
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->entries);

        $fetchData['amount'] = '2.001';
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->entries);

        $fetchData['amount'] = '-2.0';
        $response = $this->json(
            'post', 'api/v1/ledger/entry/query', $fetchData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(2, $actual->entries);

    }

}
