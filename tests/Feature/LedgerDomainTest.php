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
 * Test Ledger Domain API calls.
 */
class LedgerDomainTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public array $baseRequest = [
        'code' => 'ENG',
        'names' => [
            [
                'name' => 'Engineering',
                'language' => 'en'
            ]
        ],
        'currency' => 'CAD'
    ];


    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'domain';
    }

    public function testBadRequest()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->postJson(
            'api/v1/ledger/domain/add', ['nonsense' => true]
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
            'post', 'api/v1/ledger/domain/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->domain);
        $this->hasAttributes(['code', 'currency', 'names'], $actual->domain);
        $this->assertEquals('ENG', $actual->domain->code);
        $this->assertEquals('CAD', $actual->domain->currency);
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
            'post', 'api/v1/ledger/domain/add', $this->baseRequest
        );
        // Add SJ again
        $response = $this->json(
            'post', 'api/v1/ledger/domain/add', $this->baseRequest
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
            'post', 'api/v1/ledger/domain/add', $this->baseRequest
        );
        $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'ENG',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/domain/delete', $requestData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/v1/ledger/domain/get', $requestData
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

        // Now fetch the default domain
        $requestData = [
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(
            ['code', 'currency', 'names'],
            $actual->domain
        );
        $this->hasRevisionElements($actual->domain);
        $this->assertEquals('Corp', $actual->domain->code);
        $this->assertEquals('CAD', $actual->domain->currency);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/v1/ledger/domain/get', $requestData
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

        // Verify the default domain is as expected
        $rules = LedgerAccount::rules();
        $this->assertEquals('Corp', $rules->domain->default);

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/domain/update', $requestData
        );
        $this->isFailure($response);

        // Do a get so we have a valid revision
        $response = $this->json(
            'post', 'api/v1/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now try with a valid revision
        $requestData = [
            'revision' => $actual->domain->revision,
            'code' => 'Corp',
            'toCode' => 'Main'
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/domain/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('MAIN', $result->domain->code);
        $this->assertEquals('CAD', $result->domain->currency);

        // Attempt a retry with the same (now invalid) revision.
        $response = $this->json(
            'post', 'api/v1/ledger/domain/update', $requestData
        );
        $this->isFailure($response);

        // Make sure the default domain has been updated
        $rules = LedgerAccount::rules();
        $this->assertEquals('MAIN', $rules->domain->default);
    }

}
