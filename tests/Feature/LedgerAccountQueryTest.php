<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class LedgerAccountQueryTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use PageLoader;
    use RefreshDatabase;
    use ValidatesJson;

    private function getPagedAccounts(array $requestData): array
    {
        return $this->getPages(
            'api/ledger/account/query',
            $requestData,
            'accountquery-response',
            'accounts',
            function (&$requestData, $resources) {
                $requestData['after'] = ['code' => end($resources)->code];
            }
        );

    }

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'accounts';
    }

    public function testQuery()
    {
        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        // Query for everything, paginated
        $requestData = [
            'limit' => 20,
        ];
        [$pages, $totalAccounts] = $this->getPagedAccounts($requestData);
        $actualAccounts = LedgerAccount::count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
        //print_r($accounts[0]);
    }

    /**
     * @throws Breaker
     */
    public function testQueryByName()
    {
        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        // Query for journals containing 9, paginated
        $requestData = [
            'limit' => 10,
            'names' => [
                [
                    'name' => 'Telephone'
                ],
                [
                    'name' => '%tax%',
                    'like' => true,
                ],
                [
                    'name' => '%prop%',
                    'exclude' => true,
                    'like' => true,
                ],
            ],
        ];
        [$pages, $totalAccounts] = $this->getPagedAccounts($requestData);

        $actualAccounts = 9;
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($actualAccounts, $totalAccounts);
        $this->assertEquals($expectedPages, $pages);
    }

    public function testQueryNoLedger()
    {
        // Query for everything, paginated
        $requestData = [
            'limit' => 20,
        ];
        $response = $this->json(
            'post', 'api/ledger/account/query', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testQueryRangeOpenBegin()
    {
        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        // Query for income accounts, paginated
        $requestData = [
            'limit' => 20,
            'rangeEnding' => '1999',
        ];
        [$pages, $totalAccounts] = $this->getPagedAccounts($requestData);
        $actualAccounts = LedgerAccount::where('code', '<=', '1999')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    public function testQueryRangeOpenEnd()
    {
        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        // Query for income accounts, paginated
        $requestData = [
            'limit' => 20,
            'range' => '6000',
        ];
        [$pages, $totalAccounts] = $this->getPagedAccounts($requestData);
        $actualAccounts = LedgerAccount::where('code', '>=', '6000')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    public function testQueryRanged()
    {
        // First we need a ledger
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        // Query for income accounts, paginated
        $requestData = [
            'limit' => 20,
            'range' => '4000',
            'rangeEnding' => '4999',
        ];
        [$pages, $totalAccounts] = $this->getPagedAccounts($requestData);
        $actualAccounts = LedgerAccount::whereBetween('code', ['4000', '4999'])
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
        //print_r($accounts[0]);
    }

}
