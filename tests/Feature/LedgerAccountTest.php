<?php /** @noinspection ALL */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Root\Flex;
use Abivia\Ledger\Tests\TestCase;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class LedgerAccountTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;
    use ValidatesJson;

    protected function addAccount(string $code, string $parentCode, bool $debit)
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
        $requestData[$debit ? 'debit' : 'credit'] = true;
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );

        return $this->isSuccessful($response);
    }

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'account';
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
            ],
            "debit" => true,
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'account-response');
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
        // Check the response against our schema
        $this->validateResponse($actual, 'entry-response');
    }

    public function testAddBadName()
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
            ],
            "debit" => true,
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Try adding an account with the same name in en-Yo
        $requestData = [
            'code' => '1020',
            'parent' => [
                'code' => '1000',
            ],
            'name' => 'Cash near the Bank',
            'names' => [
                [
                    'name' => 'Cash Stash',
                    'language' => 'en-YO',
                ]
            ],
            "debit" => true,
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

    public function testAddNoLedger()
    {
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
            ],
            "debit" => true,
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testAddToEmpty()
    {
        // First we need a ledger
        $this->createLedger(['template']);

        // Add an account
        $requestData = [
            'code' => '1000',
            'name' => 'Cash in Bank',
            'debit' => true,
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->account);
        $this->hasAttributes(['uuid', 'code', 'names'], $actual->account);
        $this->assertEquals('1000', $actual->account->code);
        $this->assertCount(1, $actual->account->names);
        $this->assertEquals(
            'Cash in Bank',
            $actual->account->names[0]->name
        );
        $this->assertEquals(
            'en',
            $actual->account->names[0]->language
        );
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
        $root = LedgerAccount::root();
        $this->assertTrue($root->category);
        /** @var Flex $flex */
        $flex = $root->flex;
        $this->assertEquals('CORP', $flex->rules->domain->default);
        $this->assertEquals([1, 2, 3], $flex->rules->_myAppRule);
        $this->assertEquals('en', $flex->rules->language->default);
        $this->assertEquals(25, $flex->rules->pageSize);
        $this->assertEquals([], $flex->rules->sections);
    }

    /**
     * Create a valid ledger
     *
     * @return void
     * @throws Exception
     */
    public function testCreateNoRules(): void
    {
        $response = $this->createLedger(['rules']);

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();
        $root = LedgerAccount::root();
        $this->assertTrue($root->category);
        /** @var Flex $flex */
        $flex = $root->flex;
        $this->assertEquals('CORP', $flex->rules->domain->default);
        $this->assertEquals('en', $flex->rules->language->default);
        $this->assertEquals(25, $flex->rules->pageSize);
        $this->assertEquals([], $flex->rules->sections);
    }

    /**
     * Create a more complex ledger and test parent links
     *
     * @return void
     * @throws Exception
     */
    public function testCreateCommon(): void
    {
        $response = $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

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
     * Create a more complex ledger and test parent links
     *
     * @return void
     * @throws Exception
     */
    public function testCreateSectionOverride(): void
    {
        $response = $this->createLedger(
            ['template'],
            [
                'template' => 'manufacturer_1.0',
                'sections' => [
                    [
                        'name' => 'Accounts Receivable',
                        'codes' => '2100',
                    ],
                    [
                        'name' => 'Other Liabilities',
                        'codes' => '2200',
                    ],
                ],
            ]
        );

        $this->isSuccessful($response, 'ledger');

        $rules = LedgerAccount::rules();

        $sections = $rules->sections;
        foreach ($sections as &$section) {
            unset($section->ledgerUuids);
            $section = (array)$section;
            foreach ($section['names'] as &$name) {
                $name = (array)$name;
            }
        }
        $expect = [
            [
                "codes" => ['1100', '1200', '1300'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Current Assets"
                    ]
                ]
            ],
            [
                "codes" => ['1400'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Inventory"
                    ]
                ],
            ],
            [
                "codes" => ['1500'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Other Short Term Assets"
                    ]
                ]
            ],
            [
                "codes" => ['1600', '1700', '1800'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Capital Assets"
                    ]
                ]
            ],
            [
                "codes" => ['2100'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Accounts Receivable"
                    ]
                ]
            ],
            [
                "codes" => ['2200'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Other Liabilities"
                    ]
                ]
            ],
            [
                "codes" => ['2300'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Long Term Liabilities"
                    ]
                ]
            ],
            [
                "codes" => ['3100'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Share Capital"
                    ]
                ]
            ],
            [
                "codes" => ['3200'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Retained Earnings"
                    ]
                ]
            ],
            [
                "codes" => ['4100'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Sales Revenue"
                    ]
                ]
            ],
            [
                "codes" => ['4200'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Other Revenue"
                    ]
                ]
            ],
            [
                "codes" => ['5000'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Cost of Sales"
                    ]
                ]
            ],
            [
                "codes" => ['6000'],
                "names" => [
                    [
                        "language" => "en",
                        "name" => "Expenses"
                    ]
                ]
            ]
        ];
        $this->assertEquals($expect, $sections);
    }

    /**
     * Create a more complex ledger and test parent links
     *
     * @return void
     * @throws Exception
     */
    public function testCreateSectionOverrideBad(): void
    {
        $response = $this->createLedger(
            ['template'],
            [
                'template' => 'manufacturer_1.0',
                'sections' => [
                    [
                        'name' => 'Accounts Erroneous',
                        'codes' => '4321',
                    ],
                ],
            ],
            true
        );

        $this->isFailure($response, 'ledger');
    }

    /**
     * Create a more complex ledger and test parent links
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
                        'code' => '1000',
                        'name' => 'Assets',
                        'category' => true,
                        'debit' => true,
                    ],
                    [
                        'code' => '1100',
                        'name' => 'Chequing Account',
                        'parent' => '1000',
                        'debit' => true,
                    ],
                ],
            ]
        );

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();

        // Get the accounts
        $account = LedgerAccount::where('code', '1000')->first();
        $this->assertNotNull($account);
        $this->assertEquals('1000', $account->code);
        $account = LedgerAccount::where('code', '1100')->first();
        $this->assertNotNull($account);
        $this->assertEquals('1100', $account->code);
        $parent = LedgerAccount::find($account->parentUuid);
        $this->assertEquals('1000', $parent->code);
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
                ['code' => '1120', 'amount' => '-3000', 'currency' => 'CAD'],
                // Savings
                ['code' => '1130', 'amount' => '-10000', 'currency' => 'CAD'],
                // A/R
                ['code' => '1310', 'amount' => '-1500', 'currency' => 'CAD'],
                // Retained earnings
                ['code' => '3200', 'amount' => '14000', 'currency' => 'CAD'],
                // A/P
                ['code' => '2120', 'amount' => '500', 'currency' => 'CAD'],
            ],
            'template' => 'manufacturer_1.0'
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
                ['code' => '1120', 'amount' => '-3000', 'currency' => 'CAD'],
                // Savings
                ['code' => '1130', 'amount' => '-10000', 'currency' => 'CAD'],
                // A/R
                ['code' => '1310', 'amount' => '-1500', 'currency' => 'CAD'],
            ],
            'template' => 'manufacturer_1.0'
        ];
        $response = $this->createLedger(['template'], $balancePart, true);

        $this->isFailure($response);
    }

    /**
     * Create a ledger with a preset account
     *
     * @return void
     * @throws Exception
     */
    public function testCreateWithTemplateAndAccounts(): void
    {
        $response = $this->createLedger(
            ['template'],
            [
                'accounts' => [
                    [
                        'names' => [
                            [
                                'name' => 'Assets',
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

    public function testDelete()
    {
        // First we need a ledger and an account
        $this->createLedger();
        $addResult = $this->addAccount('1010', '1000', true);

        // Now delete the account
        $requestData = [
            'code' => '1010',
            'revision' => $addResult->account->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/account/delete', $requestData
        );
        $actual =$this->isSuccessful($response, 'success');
        // Check the response against our schema
        $this->validateResponse($actual, 'entry-response');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testDeleteSubAccounts()
    {
        // First we need a ledger
        $this->createLedger();

        // Add an account and a few sub-accounts
        $addResult = $this->addAccount('1010', '1000', true);
        $this->addAccount('1011', '1010', true);
        $this->addAccount('1012', '1010', true);

        // Now delete the parent account
        $requestData = [
            'code' => '1010',
            'revision' => $addResult->account->revision,
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
        $this->addAccount('1010', '1000', true);

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
        $requestData = ['code' => '1010', 'uuid' => $uuid];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isSuccessful($response);

        // Expect error when no code/uuid provided
        $requestData = ['bogus' => '9999'];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with code mismatch
        $requestData = ['code' => '9999', 'uuid' => $uuid];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with bad uuid
        $requestData = ['uuid' => 'bob'];
        $response = $this->json(
            'post', 'api/ledger/account/get', $requestData
        );
        $this->isFailure($response);

        // Expect error with bad code
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
        $accountInfo = $this->addAccount('1010', '1000', true);

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => '1010',
            'credit' => true,
            'taxCode' => '1.1.1',
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
        $this->assertEquals('1.1.1', $result->account->taxCode);

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

    public function testUpdateBadName()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some accounts
        $account1010 = $this->addAccount('1010', '1000', true);
        $account1020 = $this->addAccount('1020', '1000', true);

        // Try giving the second account the same name as the first
        $requestData = [
            'revision' => $account1010->account->revision,
            'code' => '1010',
            'names' => [
                [
                    'name' => "Account 1020 with parent 1000",
                    'language' => 'en',
                ]
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $result = $this->isFailure($response);
    }

    public function testUpdateCode()
    {
        // First we need a ledger
        $this->createLedger();

        // Add an account
        $accountInfo = $this->addAccount('1010', '1000', true);

        // Cahnge the code to 1011
        $requestData = [
            'revision' => $accountInfo->account->revision,
            'code' => '1010',
            'credit' => true,
            'toCode' => '1011',
        ];
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('1011', $result->account->code);

        // Add second account to check duplicates
        $this->addAccount('1012', '1000', true);
        $requestData['code'] = '1011';
        $requestData['toCode'] = '1012';
        $requestData['revision'] = $result->account->revision;
        $response = $this->json(
            'post', 'api/ledger/account/update', $requestData
        );
        $result = $this->isFailure($response);
    }

}
