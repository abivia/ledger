<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCase;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class LedgerAccountTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'account';
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
            'post', 'api/ledger/account/add', $requestData
        );

        return $this->isSuccessful($response);
    }

    public function testBadRequest()
    {
        $response = $this->postJson(
            'api/ledger/root/create', ['nonsense' => true]
        );
        $this->isFailure($response);
    }

    /**
     * Create a valid ledger
     *
     * @return void
     * @throws Exception
     */
    public function testCreate(): void
    {
        $response = $this->createLedger();

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();
        //print_r(LedgerAccount::root());
    }

    /**
     * Create a more complex ledger and test parent links
     *
     * @return void
     * @throws Exception
     */
    public function testCreateCommon(): void
    {
        $response = $this->createLedger(['template'], ['template' => 'manufacturer']);

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();

        // Get a sub-sub account
        $account = LedgerAccount::where('code', '2110')->first();
        $parent = LedgerAccount::find($account->parentUuid);
        $this->assertEquals('2100', $parent->code);
        $parent = LedgerAccount::find($parent->parentUuid);
        $this->assertEquals('2000', $parent->code);
        $parent = LedgerAccount::find($parent->parentUuid);
        $this->assertEquals('', $parent->code);
    }

    /**
     * Create a ledger with a preset account
     *
     * @return void
     * @throws Exception
     */
    public function testCreateWithAccounts(): void
    {
        $response = $this->createLedger(
            ['template'],
            [
            'accounts' => [
                [
                    'names' => [
                        [
                            'name' =>'Assets',
                            'language' => 'en',
                        ],
                    ],
                    'code' => '1100',
                    'parent' => [
                        'code' => '1000',
                    ],
                    'debit' => true,
                ]
            ],
            'template' => 'sections'
        ]);

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();

        // Get the sub-sub account and make sure it's connected correctly.
        $account = LedgerAccount::where('code', '1100')->first();
        $parent = LedgerAccount::find($account->parentUuid);
        $this->assertEquals('1000', $parent->code);
    }

    /**
     * Attempt to create a ledger with no currencies.
     *
     * @return void
     * @throws Exception
     */
    public function testCreateNoCurrency(): void
    {
        $badRequest = $this->createRequest;
        unset($badRequest['currencies']);
        $response = $this->postJson(
            'api/ledger/root/create', $badRequest
        );

        $this->isFailure($response);
        $this->assertEquals(
            'At least one currency is required.',
            $response['errors'][1]
        );
    }

    /**
     * Create a valid ledger
     *
     * @return void
     * @throws \Exception
     */
    public function testCreateWithBalances(): void
    {
        $balancePart = [
            'balances' => [
                // Cash in bank
                [ 'code' => '1120', 'amount' => '-3000', 'currency' => 'CAD'],
                // Savings
                [ 'code' => '1130', 'amount' => '-10000', 'currency' => 'CAD'],
                // A/R
                [ 'code' => '1310', 'amount' => '-1500', 'currency' => 'CAD'],
                // Retained earnings
                [ 'code' => '3200', 'amount' => '14000', 'currency' => 'CAD'],
                // A/P
                [ 'code' => '2120', 'amount' => '500', 'currency' => 'CAD'],
            ],
            'template' => 'manufacturer'
        ];
        $response = $this->createLedger(['template'], $balancePart);

        $this->isSuccessful($response, 'ledger');

        //$this->dumpLedger();
    }

    /**
     * Create a valid ledger
     *
     * @return void
     * @throws \Exception
     */
    public function testCreateWithBalances_bad(): void
    {
        $balancePart = [
            'balances' => [
                // Cash in bank
                [ 'code' => '1120', 'amount' => '-3000', 'currency' => 'CAD'],
                // Savings
                [ 'code' => '1130', 'amount' => '-10000', 'currency' => 'CAD'],
                // A/R
                [ 'code' => '1310', 'amount' => '-1500', 'currency' => 'CAD'],
            ],
            'template' => 'manufacturer'
        ];
        $response = $this->createLedger(['template'], $balancePart, true);

        $this->isFailure($response);
    }

    public function testAdd()
    {
        // First we need a ledger
        $this->createLedger();

        // Add an account
        $requestData = [
            'code' => '1010',
            'parent' => [
                'code' => '1000',
            ],
            'name' => 'Cash in Bank',
            'names' => [
                [
                    'name' => 'Cash Stash',
                    'language' => 'en-YO',
                ]
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->account);
        $this->hasAttributes(['uuid', 'code', 'names'], $actual->account);
        $this->assertEquals('1010', $actual->account->code);
        $this->assertCount(2, $actual->account->names);
        $this->assertEquals(
            'Cash in Bank',
            $actual->account->names[0]->name
        );
        $this->assertEquals(
            'en',
            $actual->account->names[0]->language
        );
        $this->assertEquals(
            'Cash Stash',
            $actual->account->names[1]->name
        );
        $this->assertEquals(
            'en-YO',
            $actual->account->names[1]->language
        );
    }

    public function testAddBadCode()
    {
        // First we need a ledger
        $this->createLedger();

        // Add an account
        $requestData = [
            'code' => '10b/76',
            'parent' => [
                'code' => '1000',
            ],
            'names' => [
                [
                    'name' => 'Cash in Bank',
                    'language' => 'en',
                ]
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testAddDuplicate()
    {
        // First we need a ledger
        $response = $this->postJson(
            'api/ledger/root/create', $this->createRequest
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
            'post', 'api/ledger/account/add', $requestData
        );
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isFailure($response);
        //print_r($actual);
    }

    public function testDelete()
    {
        // First we need a ledger and an account
        $this->createLedger();
        $this->addAccount('1010', '1000');

        // Now delete the account
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/ledger/account/delete', $requestData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $actual = $this->isFailure($response, 'accounts');
    }

    public function testDeleteSubAccounts()
    {
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
            'post', 'api/ledger/account/delete', $requestData
        );
        $this->isSuccessful($response, 'success');
    }

    public function testGet()
    {
        // First we need a ledger and an account
        $this->createLedger();
        $this->addAccount('1010', '1000');

        // Now fetch the account
        $requestData = [
            'code' => '1010',
        ];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
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
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isSuccessful($response);

        // Now fetch with uuid and correct code
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '1010', 'uuid' => $uuid];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isSuccessful($response);

        // Expect error when no code/uuid provided
        $uuid = $actual->account->uuid;
        $requestData = ['bogus' => '9999'];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with code mismatch
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '9999', 'uuid' => $uuid];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with bad uuid
        $uuid = $actual->account->uuid;
        $requestData = ['uuid' => 'bob'];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with bad code
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '9999'];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);
    }

    public function testLoadNonexistentRoot()
    {
        LedgerAccount::loadRoot();
        $this->expectException(Exception::class);
        LedgerAccount::root();
    }

    /**
     * TODO: create a separate test suite for structural updates (parent, category, etc).
     */
    public function testUpdate()
    {
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
            'post', 'api/ledger/account/update', $requestData
        );
        $this->isFailure($response);

        // Now try with a valid revision
        $requestData['revision'] = $accountInfo->account->revision;
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);

        // Attempt a retry with the same (now invalid) revision.
        $requestData['revision'] = $accountInfo->account->revision;
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $this->isFailure($response);

        // Try again with a valid revision
        $requestData['revision'] = $result->account->revision;
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);

        // Try setting both debit and credit true
        $requestData['debit'] = true;
        $requestData['revision'] = $result->account->revision;
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $this->isFailure($response);

        unset($requestData['credit']);
        unset($requestData['debit']);
        $requestData['names'] = [
            ['name' => 'Updated Name', 'language' => 'en'],
            ['name' => 'Additional Name', 'language' => 'en-ca'],
        ];
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertCount(2, $result->account->names);
    }

}
