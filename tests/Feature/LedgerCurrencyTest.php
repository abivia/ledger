<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;


use Abivia\Ledger\Http\Controllers\LedgerCurrencyController;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger Currency API calls.
 */
class LedgerCurrencyTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use PageLoader;
    use RefreshDatabase;
    use ValidatesJson;

    private function createCurrencies()
    {
        $controller = new LedgerCurrencyController();
        for ($id = 0; $id < 30; ++$id) {
            $data = [
                'code' => 'C' . str_pad($id, 2, '0', STR_PAD_LEFT),
                'decimals' => 2
            ];
            $controller->add(Currency::fromArray($data));
        }
    }

    private function getPagedCurrencies(array $requestData): array
    {
        return $this->getPages(
            'api/ledger/currency/query',
            $requestData,
            'currencyquery-response',
            'currencies',
            function (&$requestData, $resources) {
                $requestData['after'] = end($resources)->code;
            }
        );

    }

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'currency';
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
        // Check the response against our schema
        $this->validateResponse($actual, 'currency-response');
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

    public function testAddNoLedger()
    {
        // Add a currency
        $requestData = [
            'code' => 'fud',
            'decimals' => 4
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/add', $requestData
        );
        $actual = $this->isFailure($response);
    }

    public function testBadRequest()
    {
        $response = $this->postJson(
            'api/ledger/currency/add', ['nonsense' => true]
        );
        $actual = $this->isFailure($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'currency-response');
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
            'revision' => $actual->currency->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/currency/delete', $requestData
        );
        $actual = $this->isSuccessful($response, 'success');
        // Check the response against our schema
        $this->validateResponse($actual, 'currency-response');

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

    public function testQuery()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test currencies
        $this->createCurrencies();

        // Query for everything, paginated
        $requestData = [
            'limit' => 20,
        ];
        [$pages, $totalAccounts] = $this->getPagedCurrencies($requestData);
        $actualAccounts = LedgerCurrency::count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    public function testQueryRange()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test currencies
        $this->createCurrencies();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 3,
            'range' => 'C10',
            'rangeEnding' => 'C19',
        ];
        [$pages, $totalAccounts] = $this->getPagedCurrencies($requestData);
        $actualAccounts = LedgerCurrency::whereBetween('code', ['C10', 'C19'])
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    public function testQueryRangeOpenBegin()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test currencies
        $this->createCurrencies();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 5,
            'rangeEnding' => 'C19',
        ];
        [$pages, $totalAccounts] = $this->getPagedCurrencies($requestData);
        $actualAccounts = LedgerCurrency::where('code', '<=', 'C19')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    public function testQueryRangeOpenEnd()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test currencies
        $this->createCurrencies();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 10,
            'range' => 'C60',
        ];
        [$pages, $totalAccounts] = $this->getPagedCurrencies($requestData);
        $actualAccounts = LedgerCurrency::where('code', '>=', 'C60')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
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
