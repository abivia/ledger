<?php /** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Models\LedgerCurrency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Test Ledger Currency API calls.
 */
class LedgerCurrencyTest extends TestCase
{
    use CreateLedgerTrait;
    use RefreshDatabase;

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
        string $expect = 'currency'
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
            'api/v1/ledger/currency/add', ['nonsense' => true]
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

        // Add a currency
        $requestData = [
            'code' => 'fud',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/add', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->currency);
        $this->hasAttributes(['code', 'decimals'], $actual->currency);
        $this->assertEquals('FUD', $actual->currency->code);
        $this->assertEquals(4, $actual->currency->decimals);
    }

    public function testAddDuplicate()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Add CAD again
        $requestData = [
            'code' => 'CAD',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/add', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testDelete()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger and an account
        $this->createLedger();

        // Add a currency
        $requestData = [
            'code' => 'fud',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/add', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'FUD',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/delete', $requestData
        );
        $actual = $this->isSuccessful($response, 'success');

        // Confirm that a fetch fails
        $requestData = [
            'code' => 'FUD',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/get', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testGet()
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger();

        // Now fetch the currency
        $requestData = [
            'code' => 'CAD',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(['code', 'decimals'], $actual->currency);
        $this->hasRevisionElements($actual->currency);
        $this->assertEquals('CAD', $actual->currency->code);
        $this->assertEquals(2, $actual->currency->decimals);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/get', $requestData
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

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => 'CAD',
        ];
        $response = $this->json(
            'post', 'api/v1/ledger/currency/update', $requestData
        );
        $this->isFailure($response);

        // Do a get so we have a valid revision
        $response = $this->json(
            'post', 'api/v1/ledger/currency/get', $requestData
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
            'post', 'api/v1/ledger/currency/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('BOB', $result->currency->code);
        $this->assertEquals(4, $result->currency->decimals);

        // Attempt a retry with the same (now invalid) revision.
        $response = $this->json(
            'post', 'api/v1/ledger/currency/update', $requestData
        );
        $this->isFailure($response);
    }

}
