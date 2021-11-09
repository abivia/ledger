<?php
/** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\SubJournal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test Ledger Domain API calls.
 */
class SubJournalTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public array $baseRequest = [
        'code' => 'SJ',
        'names' => [
            [
                'name' => 'Sales Journal',
                'language' => 'en'
            ]
        ]
    ];


    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'journal';
    }

    public function testBadRequest()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->postJson(
            'api/v1/ledger/journal/add', ['nonsense' => true]
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

        // Add a sub-journal
        $response = $this->json(
            'post', 'api/v1/ledger/journal/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->journal);
        $this->hasAttributes(['code', 'names'], $actual->journal);
        $this->assertEquals('SJ', $actual->journal->code);
    }

    public function testAddDuplicate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add SJ
        $this->json(
            'post', 'api/v1/ledger/journal/add', $this->baseRequest
        );
        // Add SJ again
        $response = $this->json(
            'post', 'api/v1/ledger/journal/add', $this->baseRequest
        );
        $this->isFailure($response);
    }

    public function testDelete()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add a sub-journal
        $response = $this->json(
            'post', 'api/v1/ledger/journal/add', $this->baseRequest
        );
        $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'SJ',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/journal/delete', $requestData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/v1/ledger/journal/get', $requestData
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

        // Add a sub-journal
        $this->json(
            'post', 'api/v1/ledger/journal/add', $this->baseRequest
        );

        // Now fetch the sub-journal again
        $requestData = [
            'code' => 'SJ',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/journal/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(
            ['code', 'names'],
            $actual->journal
        );
        $this->hasRevisionElements($actual->journal);
        $this->assertEquals('SJ', $actual->journal->code);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/v1/ledger/journal/get', $requestData
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

        // Add a sub-journal
        $this->json(
            'post', 'api/v1/ledger/journal/add', $this->baseRequest
        );

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => 'SJ',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/journal/update', $requestData
        );
        $this->isFailure($response);

        // Do a get so we have a valid revision
        $response = $this->json(
            'post', 'api/v1/ledger/journal/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now try with a valid revision
        $requestData = [
            'revision' => $actual->journal->revision,
            'code' => 'SJ',
            'toCode' => 'EJ'
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/journal/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('EJ', $result->journal->code);

        // Attempt a retry with the same (now invalid) revision.
        $requestData['code'] = 'EJ';
        $response = $this->json(
            'post', 'api/v1/ledger/journal/update', $requestData
        );
        $this->isFailure($response);

    }

}
