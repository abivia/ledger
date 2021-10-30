<?php /** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class LedgerAccountTest extends TestCase
{
    use RefreshDatabase;

    protected array $createRequest = [
        'name' => 'Test Ledger',
        'language' => 'en-CA',
        'domains' => [
            [
                'code' => 'GL',
                'names' => [
                    [
                        'name' => 'General Ledger',
                        'language' => 'en-CA'
                    ],
                    [
                        'name' => 'Grand Livre',
                        'language' => 'fr-CA'
                    ]
                ]
            ]
        ],
        'currencies' => [
            [
                'code' => 'CAD',
                'decimals' => 2
            ]
        ],
        'names' => [
            [
                'name' => 'General Ledger Test',
                'language' => 'en-CA'
            ],
            [
                'name' => 'Tester le grand livre',
                'language' => 'fr-CA'
            ]
        ],
        'rules' => [
            'account' => [
                'codeFormat' => '/[a-z0-9\-]+/i'
            ]
        ],
        'extra' => 'arbitrary JSON',
        'template' => 'sections'
    ];

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

    protected function createLedger()
    {
        $response = $this->postJson(
            'api/v1/ledger/create', $this->createRequest
        );
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertFalse(isset($response['errors']));

        return $response;
    }

    private function hasAttributes(array $attributes, object $object)
    {
        foreach ($attributes as $attribute) {
            $this->assertObjectHasAttribute($attribute, $object);
        }
    }

    private function hasRevisionElements(object $account)
    {
        $this->assertTrue(isset($account->revision));
        $this->assertTrue(isset($account->createdAt));
        $this->assertTrue(isset($account->updatedAt));
    }

    private function isFailure(TestResponse $response)
    {
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertTrue(isset($response['errors']));
        $actual = json_decode($response->content());
        $this->assertTrue($actual !== false);
        $this->assertCount(2, (array)$actual);

        return $actual;
    }

    /**
     * Make sure the response was not an error and is well-structured.
     * @param TestResponse $response
     * @param string $expect
     * @return mixed Decoded JSON response
     */
    private function isSuccessful(
        TestResponse $response,
        string $expect = 'account'
    )
    {
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertFalse(isset($response['errors']));
        $this->assertTrue(isset($response[$expect]));
        $actual = json_decode($response->content());
        $this->assertTrue($actual !== false);

        return $actual;
    }

    public function testBadRequest()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->postJson(
            'api/v1/ledger/create', ['nonsense' => true]
        );
        $this->isFailure($response);
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
        $response = $this->createLedger();

        $this->isSuccessful($response, 'ledger');

        LedgerAccount::loadRoot();
        //print_r(LedgerAccount::root());
    }

    /**
     * Attempt to create a ledger with no currencies.
     *
     * @return void
     * @throws \Exception
     */
    public function testCreateNoCurrency(): void
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );
        $badRequest = $this->createRequest;
        unset($badRequest['currencies']);
        $response = $this->postJson(
            'api/v1/ledger/create', $badRequest
        );

        $this->isFailure($response);
        $this->assertEquals(
            'At least one currency is required.',
            $response['errors'][0]
        );
    }

    public function testAdd()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add an account
        $requestData = [
            'code' => '1010',
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
            'post', 'api/v1/ledger/account/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->account);
        $this->hasAttributes(['uuid', 'code', 'names'], $actual->account);
        $this->assertEquals('1010', $actual->account->code);
        $this->assertEquals(
            'Cash in Bank',
            $actual->account->names[0]->name
        );
        $this->assertEquals(
            'en',
            $actual->account->names[0]->language
        );
    }

    public function testAddDuplicate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $response = $this->postJson(
            'api/v1/ledger/create', $this->createRequest
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
        print_r($actual);
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
        $actual = $this->isSuccessful($response, 'accounts');

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
        $actual = $this->isSuccessful($response, 'accounts');
        $this->assertCount(3, $actual->accounts);
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
