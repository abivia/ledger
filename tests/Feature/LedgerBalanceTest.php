<?php /** @noinspection ALL */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCase;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger API balance calls.
 */
class LedgerBalanceTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;
    use ValidatesJson;

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'balance';
    }

    /**
     * Create a ledger with some balances, then query
     *
     * @return void
     * @throws \Exception
     */
    public function testGetBalances(): void
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
            'template' => 'manufacturer_1.0'
        ];
        $response = $this->createLedger(['template'], $balancePart);

        $this->isSuccessful($response, 'ledger');

        // Get an account with a balance
        $requestData = [
            'code' => '1120',
            'currency' => 'CAD'
        ];
        $response = $this->json(
            'post', 'api/ledger/balance/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'balance-response');
        $this->assertEquals('-3000.00', $actual->balance->amount);

        // Get an account with no balance
        $requestData = [
            'code' => '6830',
            'currency' => 'CAD'
        ];
        $response = $this->json(
            'post', 'api/ledger/balance/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->assertEquals('0.00', $actual->balance->amount);
    }

    /**
     * Create a ledger with some balances, then get nonexistent account
     *
     * @return void
     * @throws \Exception
     */
    public function testGetBalances_bad(): void
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
            'template' => 'manufacturer_1.0'
        ];
        $response = $this->createLedger(['template'], $balancePart);

        $this->isSuccessful($response, 'ledger');

        // Try to get a nonexistent account
        $requestData = [
            'code' => '6666',
            'currency' => 'CAD'
        ];
        $response = $this->json(
            'post', 'api/ledger/balance/get', $requestData
        );
        $actual = $this->isFailure($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'balance-response');
    }

}
