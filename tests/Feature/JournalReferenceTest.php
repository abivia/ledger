<?php
/** @noinspection PhpParamsInspection */
declare(strict_types=1);

namespace Abivia\Ledger\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Abivia\Ledger\Tests\TestCase;

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
        $response = $this->postJson(
            'api/ledger/reference/add', ['nonsense' => true]
        );
        $this->isFailure($response);
    }

    public function testAdd()
    {
        //Create a ledger
        $this->createLedger();

        // Add a domain
        $response = $this->json(
            'post', 'api/ledger/reference/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['code', 'extra'], $actual->reference);
        $this->assertEquals('Customer 25', $actual->reference->code);
    }

    public function testAddDuplicate()
    {
        // First we need a ledger
        $this->createLedger();

        // Add a reference
        $this->json(
            'post', 'api/ledger/reference/add', $this->baseRequest
        );
        // Add it again
        $response = $this->json(
            'post', 'api/ledger/reference/add', $this->baseRequest
        );
        $this->isFailure($response);
    }

    public function testDelete()
    {
        // First we need a ledger and domain
        $this->createLedger();

        // Add a domain
        $response = $this->json(
            'post', 'api/ledger/reference/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'Customer 25',
            'revision' => $actual->reference->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/reference/delete', $requestData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/ledger/reference/get', $requestData
        );
        $this->isFailure($response);
    }

    public function testGet()
    {
        // First we need a ledger
        $this->createLedger();

        // Add a reference
        $this->json(
            'post', 'api/ledger/reference/add', $this->baseRequest
        );
        // Now fetch the same reference
        $requestData = [
            'code' => 'Customer 25',
        ];
        $response = $this->json(
            'post', 'api/ledger/reference/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['code', 'extra'], $actual->reference);
        $this->assertEquals('Customer 25', $actual->reference->code);
        $extra = json_decode($actual->reference->extra);
        $this->assertEquals(25, $extra->customerId);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/ledger/reference/get', $requestData
        );
        $this->isFailure($response);
    }

    /**
     * TODO: create a separate suite for updates where transactions present.
     */
    public function testUpdate()
    {
        // First we need a ledger
        $this->createLedger();

        // Add a reference
        $response = $this->json(
            'post', 'api/ledger/reference/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);

        // Try an update on nonexistent record
        $requestData = [
            'code' => 'nobody-here',
        ];
        $response = $this->json(
            'post', 'api/ledger/reference/update', $requestData
        );
        $this->isFailure($response);

        // Now try an update on existing record
        $requestData = [
            'code' => 'Customer 25',
            'toCode' => 'Customer 25B',
            'revision' => $actual->reference->revision,
            'extra' => json_encode([
                'customerId' => 25,
                'name' => 'Testco (rev B) Inc.'
            ]),
        ];
        $response = $this->json(
            'post', 'api/ledger/reference/update', $requestData
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
