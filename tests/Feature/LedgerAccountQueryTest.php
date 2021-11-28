<?php /** @noinspection PhpParamsInspection */

namespace Tests\Feature;

use App\Models\LedgerAccount;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'common']);

        // Query for everything, paginated
        $pages = 0;
        $totalAccounts = 0;
        $requestData = [
            'limit' => 20,
        ];
        while (1) {
            $response = $this->json(
                'post', 'api/v1/ledger/account/query', $requestData
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
        print_r($accounts[0]);
    }

}
