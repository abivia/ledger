<?php
/** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\SubJournalController;
use Abivia\Ledger\Messages\SubJournal;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\SubJournal as SubJournalModel;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger Domain API calls.
 */
class SubJournalTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use PageLoader;
    use RefreshDatabase;
    use ValidatesJson;

    public array $baseRequest = [
        'code' => 'SJ',
        'names' => [
            [
                'name' => 'Sales Journal',
                'language' => 'en'
            ]
        ]
    ];


    /**
     * @throws Breaker
     */
    private function createSubJournals()
    {
        $controller = new SubJournalController();
        for ($id = 0; $id < 30; ++$id) {
            $data = [
                'code' => 'SJ' . str_pad((string) $id, 2, '0', STR_PAD_LEFT),
                'name' => "SubJournal $id",
            ];
            $controller->add(SubJournal::fromArray($data));
        }
    }

    private function getPagedSubJournals(array $requestData): array
    {
        return $this->getPages(
            'api/ledger/journal/query',
            $requestData,
            'journalquery-response',
            'journals',
            function (&$requestData, $resources) {
                $requestData['after'] = end($resources)->code;
            }
        );

    }

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'journal';
    }

    public function testBadRequest()
    {
        $response = $this->postJson(
            'api/ledger/journal/add', ['nonsense' => true]
        );
        $actual = $this->isFailure($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'journal-response');
    }

    public function testAdd()
    {
        //Create a ledger
        $this->createLedger();

        // Add a sub-journal
        $response = $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'journal-response');
        $this->hasRevisionElements($actual->journal);
        $this->hasAttributes(['code', 'names'], $actual->journal);
        $this->assertEquals('SJ', $actual->journal->code);
        $this->assertCount(1, $actual->journal->names);
        $this->assertEquals('Sales Journal', $actual->journal->names[0]->name);
    }

    public function testAddDuplicate()
    {
        // First we need a ledger
        $this->createLedger();

        // Add SJ
        $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );
        // Add SJ again
        $response = $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );
        $this->isFailure($response);
    }

    public function testAddNoLedger()
    {
        // Add a sub-journal
        $response = $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );
        $actual = $this->isFailure($response);
    }

    public function testDelete()
    {
        // First we need a ledger
        $this->createLedger();

        // Add a sub-journal
        $response = $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );
        $addResult = $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'SJ',
            'revision' => $addResult->journal->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/journal/delete', $requestData
        );
        $actual = $this->isSuccessful($response, 'success');
        // Check the response against our schema
        $this->validateResponse($actual, 'journal-response');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/ledger/journal/get', $requestData
        );
        $this->isFailure($response);
    }

    public function testGet()
    {
        // First we need a ledger
        $this->createLedger();

        // Add a sub-journal
        $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );

        // Now fetch the sub-journal again
        $requestData = [
            'code' => 'SJ',
        ];
        $response = $this->json(
            'post', 'api/ledger/journal/get', $requestData
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
            'post', 'api/ledger/journal/get', $requestData
        );
        $this->isFailure($response);
    }

    /**
     * @throws Breaker
     */
    public function testQuery()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for everything, paginated
        $requestData = [
            'limit' => 20,
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);
        $actualAccounts = SubJournalModel::count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * @throws Breaker
     */
    public function testQueryByName()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for journals containing 9, paginated
        $requestData = [
            'limit' => 10,
            'names' => [
                [
                    'name' => 'SubJournal 5'
                ],
                [
                    'name' => 'SubJournal 6',
                    'language' => 'en'
                ],
                [
                    'name' => '%nal 1%',
                    'like' => true,
                ],
                [
                    'name' => '%15%',
                    'exclude' => true,
                    'like' => true,
                ],
            ],
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);

        // Add 2 for the two single domain codes.
        $actualAccounts = 12;
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($actualAccounts, $totalAccounts);
        $this->assertEquals($expectedPages, $pages);
    }

    /**
     * @throws Breaker
     */
    public function testQueryList()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 15,
            'codes' => [
                'SJ03', ['SJ10', 'SJ19'], 'SJ25', ['', ''],
            ],
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);

        // Add 2 for the two single domain codes.
        $actualAccounts = 2 + SubJournalModel::whereBetween('code', ['SJ10', 'SJ19'])
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * @throws Breaker
     */
    public function testQueryRange()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 3,
            'range' => 'SJ10',
            'rangeEnding' => 'SJ19',
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);
        $actualAccounts = SubJournalModel::whereBetween('code', ['SJ10', 'SJ19'])
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * @throws Breaker
     */
    public function testQueryRangeExclusion()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for a closed range with an excluded sub-range, paginated
        $requestData = [
            'limit' => 5,
            'codes' => [['SJ05', 'SJ24'], ['!', 'SJ10', 'SJ19']],
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);
        $actualAccounts = 10;
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);

        // Same query, excluding one element in the expected range and one not
        $requestData['codes'][] = ['!', 'SJ21'];
        $requestData['codes'][] = ['!', 'SJ15'];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);
        $actualAccounts = 9;
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * @throws Breaker
     */
    public function testQueryRangeOpenBegin()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 5,
            'rangeEnding' => 'SJ19',
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);
        $actualAccounts = SubJournalModel::where('code', '<=', 'SJ19')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * @throws Breaker
     */
    public function testQueryRangeOpenEnd()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 10,
            'range' => 'SJ60',
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);
        $actualAccounts = SubJournalModel::where('code', '>=', 'SJ60')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * @throws Breaker
     */
    public function testQueryWild()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createSubJournals();

        // Query for journals containing 9, paginated
        $requestData = [
            'limit' => 15,
            'codes' => [['*', '9']],
        ];
        [$pages, $totalAccounts] = $this->getPagedSubJournals($requestData);

        // Add 2 for the two single domain codes.
        $actualAccounts = SubJournalModel::where('code', 'like', '%9%')
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

        // Add a sub-journal
        $this->json(
            'post', 'api/ledger/journal/add', $this->baseRequest
        );

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => 'SJ',
        ];
        $response = $this->json(
            'post', 'api/ledger/journal/update', $requestData
        );
        $this->isFailure($response);

        // Do a get so we have a valid revision
        $response = $this->json(
            'post', 'api/ledger/journal/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now try with a valid revision
        $requestData = [
            'revision' => $actual->journal->revision,
            'code' => 'SJ',
            'toCode' => 'EJ'
        ];
        $response = $this->json(
            'post', 'api/ledger/journal/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals('EJ', $result->journal->code);

        // Attempt a retry with the same (now invalid) revision.
        $requestData['code'] = 'EJ';
        $response = $this->json(
            'post', 'api/ledger/journal/update', $requestData
        );
        $this->isFailure($response);

    }

}
