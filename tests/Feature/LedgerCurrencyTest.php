<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;


use Abivia\Ledger\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger Currency API calls.
 */
class LedgerCurrencyTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'currency';
    }

    public function testBadRequest()
    {
        $response = $this->postJson(
            'api/ledger/currency/add', ['nonsense' => true]
        );
        $this->isFailure($response);
    }

    public function testAdd()
    {
        //Create a ledger
        $this->createLedger();

        // Add a currency
        $requestData = [
            'code' => 'fud',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->currency);
        $this->hasAttributes(['code', 'decimals'], $actual->currency);
        $this->assertEquals('FUD', $actual->currency->code);
        $this->assertEquals(4, $actual->currency->decimals);
    }

    public function testAddDuplicate()
    {
        // First we need a ledger
        $this->createLedger();

        // Add CAD again
        $requestData = [
            'code' => 'CAD',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/add', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testDelete()
    {
        // First we need a ledger and an account
        $this->createLedger();

        // Add a currency
        $requestData = [
            'code' => 'fud',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/add', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'FUD',
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/delete', $requestData
        );
        $actual = $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $requestData = [
            'code' => 'FUD',
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/get', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testGet()
    {
        // First we need a ledger
        $this->createLedger();

        // Now fetch the currency
        $requestData = [
            'code' => 'CAD',
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['code', 'decimals'], $actual->currency);
        $this->hasRevisionElements($actual->currency);
        $this->assertEquals('CAD', $actual->currency->code);
        $this->assertEquals(2, $actual->currency->decimals);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/ledger/currency/get', $requestData
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

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => 'CAD',
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/update', $requestData
        );
        $this->isFailure($response);

        // Do a get so we have a valid revision
        $response = $this->json(
            'post', 'api/ledger/currency/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now try with a valid revision
        $requestData = [
            'revision' => $actual->currency->revision,
            'code' => 'CAD',
            'decimals' => 4,
            'toCode' => 'bob'
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('BOB', $result->currency->code);
        $this->assertEquals(4, $result->currency->decimals);

        // Attempt a retry with the same (now invalid) revision.
        $response = $this->json(
            'post', 'api/ledger/currency/update', $requestData
        );
        $this->isFailure($response);
    }

}
