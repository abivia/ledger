<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Models\JournalReference;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Detail;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Reference;
use Abivia\Ledger\Messages\Message;
use Carbon\Carbon;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Abivia\Ledger\Tests\TestCase;
use function array_shift;

/**
 * Test entry queries incorporating a Journal Reference
 */
class JournalEntryQueryReferenceTest extends TestCase
{
    const TRANS_COUNT = 30;

    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;
    private array $referenceUses = [];
    private array $references = [];

    /**
     * @throws Exception
     */
    protected function addRandomTransactions(int $count)
    {
        // Get a list of accounts in the ledger
        $codes = [];
        foreach (LedgerAccount::all() as $account) {
            // Get rid of the root
            if ($account->code != '') {
                $codes[] = $account->code;
            }
        }

        // Create some random references
        $refs = self::TRANS_COUNT / 2;
        for ($ind = 0; $ind < $refs; ++$ind) {
            $journalReference = new JournalReference();
            $journalReference->code = 'REF' . str_pad($ind, 4, '0', STR_PAD_LEFT);
            $journalReference->save();
            $this->references[$journalReference->code] = $journalReference;
        }
        $this->referenceUses = array_fill_keys(array_keys($this->references), 0);

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

                // First detail has no reference
                $entry->details[] = new Detail(
                    new EntityRef(array_pop($shuffled)),
                    (string)($amount / 100)
                );

                // Second detail has a reference
                $detail = new Detail(
                    new EntityRef(array_pop($shuffled)),
                    (string)(-$amount / 100)
                );
                $ref = array_rand($this->references);
                $detail->reference = new Reference();
                $detail->reference->code = $ref;
                ++$this->referenceUses[$ref];
                $entry->details[] = $detail;

                $controller->add($entry);
            }
        } catch (Breaker $exception) {
            echo $exception->getMessage() . "\n"
                . implode("\n", $exception->getErrors());
        }
    }

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

    public function testQueryApiReferences()
    {
        // Query for everything, paginated
        $fetchData = [];
        foreach ($this->referenceUses as $code => $count) {
            $fetchData['reference'] = $code;
            $response = $this->json(
                'post', 'api/ledger/entry/query', $fetchData
            );
            $actual = $this->isSuccessful($response);
            $entries = $actual->entries;
            if (count($entries) !== 25) {
                $this->assertCount($count, $entries);
            } elseif ($count <= 25) {
                $this->fail('Unexpected result pagination.');
            }
        }
    }

    public function testQueryReferences()
    {
        // Query for each reference, verifying entry counts
        foreach ($this->referenceUses as $code => $count) {
            $query = new EntryQuery();
            $query->reference = new Reference();
            $query->reference->code = $code;
            $controller = new JournalEntryController();
            $entries = $controller->query($query, Message::OP_QUERY);
            $this->assertCount($count, $entries);
        }
    }

}
