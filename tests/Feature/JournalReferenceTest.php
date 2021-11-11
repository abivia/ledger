<?php
/** @noinspection PhpParamsInspection */
declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test Journal Reference API calls.
 */
class JournalReferenceTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public array $baseRequest = [
        'code' => 'Customer 25',
        'extra' => [
            'customerId' => 25,
            'name' => 'Testco Inc.'
        ],
    ];


    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'reference';
        $this->baseRequest['extra'] = json_encode($this->baseRequest['extra']);
    }

    public function testBadRequest()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->postJson(
            'api/v1/ledger/reference/add', ['nonsense' => true]
        );
        $this->isFailure($response);
    }

    public function testAdd()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        //Create a ledger
        $this->createLedger();

        // Add a domain
        $response = $this->json(
            'post', 'api/v1/ledger/reference/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['code', 'extra'], $actual->reference);
        $this->assertEquals('Customer 25', $actual->reference->code);
    }

    public function testAddDuplicate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add a reference
        $this->json(
            'post', 'api/v1/ledger/reference/add', $this->baseRequest
        );
        // Add it again
        $response = $this->json(
            'post', 'api/v1/ledger/reference/add', $this->baseRequest
        );
        $this->isFailure($response);
    }

    public function testDelete()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger and domain
        $this->createLedger();

        // Add a domain
        $response = $this->json(
            'post', 'api/v1/ledger/reference/add', $this->baseRequest
        );
        $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'Customer 25',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/reference/delete', $requestData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/v1/ledger/reference/get', $requestData
        );
        $this->isFailure($response);
    }

    public function testGet()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add a reference
        $this->json(
            'post', 'api/v1/ledger/reference/add', $this->baseRequest
        );
        // Now fetch the same reference
        $requestData = [
            'code' => 'Customer 25',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/reference/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['code', 'extra'], $actual->reference);
        $this->assertEquals('Customer 25', $actual->reference->code);
        $extra = json_decode($actual->reference->extra);
        $this->assertEquals(25, $extra->customerId);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/v1/ledger/reference/get', $requestData
        );
        $this->isFailure($response);
    }

    /**
     * TODO: create a separate suite for updates where transactions present.
     */
    public function testUpdate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add a reference
        $this->json(
            'post', 'api/v1/ledger/reference/add', $this->baseRequest
        );
        // Try an update on nonexistent record
        $requestData = [
            'code' => 'nobody-here',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/reference/update', $requestData
        );
        $this->isFailure($response);

        // Now try an update on existing record
        $requestData = [
            'code' => 'Customer 25',
            'toCode' => 'Customer 25B',
            'extra' => json_encode([
                'customerId' => 25,
                'name' => 'Testco (rev B) Inc.'
            ]),
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/reference/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('Customer 25B', $result->reference->code);
        $extra = json_decode($result->reference->extra);
        $this->assertEquals(
            'Testco (rev B) Inc.',
            $extra->name
        );
    }

}
