<?php /** @noinspection PhpUnhandledExceptionInspection */

/** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Http\Controllers\JournalEntryController;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Reference;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test entry queries incorporating a Journal Reference
 */
class JournalEntryQueryReferenceTest extends TestCaseWithMigrations
{
    const TRANS_COUNT = 30;

    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;
    use ValidatesJson;

    private array $referenceUses = [];
    private array $references = [];

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
            // Check the response against our schema
            $this->validateResponse($actual, 'entry-response');
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
