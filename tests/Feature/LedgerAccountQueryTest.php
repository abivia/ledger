<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Abivia\Ledger\Tests\TestCase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class LedgerAccountQueryTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'accounts';
    }

    public function testGet()
    {
        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'manufacturer']);

        // Query for everything, paginated
        $pages = 0;
        $totalAccounts = 0;
        $requestData = [
            'limit' => 20,
        ];
        while (1) {
            $response = $this->json(
                'post', 'api/ledger/account/query', $requestData
            );
            $actual = $this->isSuccessful($response);
            $accounts = $actual->accounts;
            ++$pages;
            $totalAccounts += count($accounts);
            if (count($accounts) !== 20) {
                break;
            }
            $requestData['after'] = ['code' => end($accounts)->code];
        }
        $this->assertEquals(7, $pages);
        $this->assertEquals(139, $totalAccounts);
        //print_r($accounts[0]);
    }

}
