<?php /** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
        $response->assertStatus(200);
        $this->assertTrue(isset($response['time']));
        $this->assertTrue(isset($response['account']));
        $this->assertFalse(isset($response['errors']));
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

        // Expect error with code mismatch
        $uuid = $actual->account->uuid;
        $requestData = ['code' => '9999', 'uuid' => $uuid];
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

}
