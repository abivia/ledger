<?php /** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerBalance;
use App\Models\LedgerDomain;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class JournalEntryTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'entry';
    }

    protected function addAccount(string $code, string $parentCode)
    {
        // Add an account
        $requestData = [
            'code' => $code,
            'parent' => [
                'code' => $parentCode,
            ],
            'names' => [
                [
                    'name' => "Account $code with parent $parentCode",
                    'language' => 'en',
                ]
            ]
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/add', $requestData
        );

        return $this->isSuccessful($response);
    }

    /**
     * Create a valid ledger
     *
     * @return void
     * @throws \Exception
     */
    public function testCreate(): void
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );
        $response = $this->createLedger([], ['template' => 'common']);

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();
        //print_r(LedgerAccount::root());
        foreach (LedgerAccount::all() as $item) {
            echo "$item->ledgerUuid $item->code ($item->parentUuid) ";
            echo $item->category ? 'cat ' : '    ';
            if ($item->debit) echo 'DR __';
            if ($item->credit) echo '__ CR';
            echo "\n";
            foreach ($item->names as $name) {
                echo "$name->name $name->language\n";
            }
        }
    }

    public function testAdd()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $response = $this->createLedger([], ['template' => 'common']);

        $this->isSuccessful($response, 'ledger');

        // Add a transaction, sales to A/R
        $requestData = [
            'currency' => 'CAD',
            'description' => 'Sold the first thing!',
            'date' => '2021-11-12',
            'details' => [
                [
                    'accountCode' => '1310',
                    'debit' => '520.00'
                ],
                [
                    'accountCode' => '4110',
                    'credit' => '520.00'
                ],
            ]
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/entry/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->entry);

        // Check that we really did do everything that was supposed to be done.
        $journalEntry = JournalEntry::find($actual->entry->id);
        $this->assertNotNull($journalEntry);
        $this->assertTrue(
            $journalEntry->transDate->equalTo(new Carbon($requestData['date']))
        );
        $this->assertEquals('CAD', $journalEntry->currency);
        $this->assertEquals($requestData['description'], $journalEntry->description);
        $ledgerDomain = LedgerDomain::find($journalEntry->domainUuid);
        $this->assertNotNull($ledgerDomain);
        $this->assertEquals('GJ', $ledgerDomain->code);

        /** @var JournalDetail $detail */
        foreach ($journalEntry->details as $detail) {
            $ledgerAccount = LedgerAccount::find($detail->ledgerUuid);
            $this->assertNotNull($ledgerAccount);
            $ledgerBalance = LedgerBalance::where([
                ['ledgerUuid', '=', $detail->ledgerUuid],
                ['domainUuid', '=', $ledgerDomain->domainUuid],
                ['currency', '=', $journalEntry->currency]]
            )->first();
            $this->assertNotNull($ledgerBalance);
            if ($ledgerAccount->code === '1310') {
                $this->assertEquals('-520.00', $ledgerBalance->balance);
            } else {
                $this->assertEquals('520.00', $ledgerBalance->balance);
            }
        }
    }

    public function testAddDuplicate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $response = $this->postJson(
            'api/v1/ledger/root/create', $this->createRequest
        );
        $this->isSuccessful($response, 'ledger');

        // Add an account
        $requestData = [
            'code' => '1010',
            'parent' => ['code' => '1000',],
            'names' => [
                [
                    'name' => 'Cash in Bank',
                    'language' => 'en',
                ]
            ]
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/add', $requestData
        );
        $response = $this->json(
            'post', 'api/v1/ledger/account/add', $requestData
        );
        $actual = $this->isFailure($response);
        //print_r($actual);
    }

    public function testDelete()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger and an account
        $this->createLedger();
        $this->addAccount('1010', '1000');

        // Now delete the account
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/delete', $requestData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $actual = $this->isFailure($response, 'accounts');
    }

    public function testDeleteSubAccounts()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add an account and a few sub-accounts
        $this->addAccount('1010', '1000');
        $this->addAccount('1011', '1010');
        $this->addAccount('1012', '1010');

        // Now delete the parent account
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/delete', $requestData
        );
        $this->isSuccessful($response, 'success');
    }

    public function testGet()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger and an account
        $this->createLedger();
        $this->addAccount('1010', '1000');

        // Now fetch the account
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['uuid', 'code', 'names'], $actual->account);
        $this->hasRevisionElements($actual->account);
        $this->assertEquals(
            'Account 1010 with parent 1000',
            $actual->account->names[0]->name
        );
        $this->assertEquals(
            'en',
            $actual->account->names[0]->language
        );

        // Now fetch by uuid
        $uuid = $actual->account->uuid;
        $requestData = ['uuid' => $uuid];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $this->isSuccessful($response);

        // Now fetch with uuid and correct code
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '1010', 'uuid' => $uuid];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $this->isSuccessful($response);

        // Expect error when no code/uuid provided
        $uuid = $actual->account->uuid;
        $requestData = ['bogus' => '9999'];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with code mismatch
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '9999', 'uuid' => $uuid];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with bad uuid
        $uuid = $actual->account->uuid;
        $requestData = ['uuid' => 'bob'];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with bad code
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '9999'];
        $response = $this->json(
            'post', 'api/v1/ledger/account/get', $requestData
        );
        $this->isFailure($response);
    }

    public function testLoadNonexistentRoot()
    {
        LedgerAccount::loadRoot();
        $this->expectException(\Exception::class);
        LedgerAccount::root();
    }

    /**
     * TODO: create a separate test suite for structural updates (parent, category, etc).
     */
    public function testUpdate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add an account
        $accountInfo = $this->addAccount('1010', '1000');

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => '1010',
            'credit' => true
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/update', $requestData
        );
        $this->isFailure($response);

        // Now try with a valid revision
        $requestData['revision'] = $accountInfo->account->revision;
        $response = $this->json(
            'post', 'api/v1/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);

        // Attempt a retry with the same (now invalid) revision.
        $requestData['revision'] = $accountInfo->account->revision;
        $response = $this->json(
            'post', 'api/v1/ledger/account/update', $requestData
        );
        $this->isFailure($response);

        // Try again with a valid revision
        $requestData['revision'] = $result->account->revision;
        $response = $this->json(
            'post', 'api/v1/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);

        // Try setting both debit and credit true
        $requestData['debit'] = true;
        $requestData['revision'] = $result->account->revision;
        $response = $this->json(
            'post', 'api/v1/ledger/account/update', $requestData
        );
        $this->isFailure($response);

        unset($requestData['credit']);
        unset($requestData['debit']);
        $requestData['names'] = [
            ['name' => 'Updated Name', 'language' => 'en'],
            ['name' => 'Additional Name', 'language' => 'en-ca'],
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertCount(2, $result->account->names);
    }

}
