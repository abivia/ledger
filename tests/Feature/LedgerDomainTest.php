<?php
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
/** @noinspection PhpParamsInspection */
declare(strict_types=1);

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerDomainController;
use Abivia\Ledger\Messages\Create;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger Domain API calls.
 */
class LedgerDomainTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use PageLoader;
    use RefreshDatabase;
    use ValidatesJson;

    public array $baseRequest = [
        'code' => 'ENG',
        'names' => [
            [
                'name' => 'Engineering',
                'language' => 'en'
            ],
            [
                'name' => 'Nerds',
                'language' => 'en-JOCK'
            ],
            [
                'name' => 'la machination',
                'language' => 'fr'
            ],
        ],
        'currency' => 'CAD'
    ];

    /**
     * @throws Breaker
     */
    private function createDomains()
    {
        $controller = new LedgerDomainController();
        for ($id = 0; $id < 30; ++$id) {
            $data = [
                'code' => 'D' . str_pad((string) $id, 2, '0', STR_PAD_LEFT),
                'currency' => 'CAD',
                'name' => "Domain $id",
            ];
            $controller->add(Domain::fromArray($data));
        }
    }

    private function getPagedDomains(array $requestData): array
    {
        return $this->getPages(
            'api/ledger/domain/query',
            $requestData,
            'domainquery-response',
            'domains',
            function (&$requestData, $resources) {
                $requestData['after'] = end($resources)->code;
            }
        );

    }

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'domain';
    }

    public function testBadRequest()
    {
        $response = $this->postJson(
            'api/ledger/domain/add', ['nonsense' => true]
        );
        $actual = $this->isFailure($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'domain-response');
    }

    public function testAdd()
    {
        //Create a ledger
        $this->createLedger();

        // Add a domain
        $response = $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );
        $actual = $this->isSuccessful($response);
        // Check the response against our schema
        $this->validateResponse($actual, 'domain-response');
        $this->hasRevisionElements($actual->domain);
        $this->hasAttributes(['code', 'currency', 'names'], $actual->domain);
        $this->assertEquals('ENG', $actual->domain->code);
        $this->assertEquals('CAD', $actual->domain->currency);
    }

    public function testAddBadName()
    {
        //Create a ledger
        $this->createLedger();

        // Add a domain
        $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );
        // Now try to add a different domain with the same name
        $badRequest = [
            'code' => 'BAD',
            'names' => [
                [
                    'name' => 'This is ok',
                    'language' => 'en'
                ],
                [
                    // This is an error
                    'name' => 'Nerds',
                    'language' => 'en-JOCK'
                ],
                [
                    // Also an error
                    'name' => 'la machination',
                    'language' => 'fr'
                ],
            ],
            'currency' => 'CAD'
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/add', $badRequest
        );
        $this->isFailure($response);
    }

    public function testAddDuplicate()
    {
        // First we need a ledger
        $this->createLedger();

        // Add SJ
        $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );
        // Add SJ again
        $response = $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );
        $this->isFailure($response);
    }

    public function testAddNoLedger()
    {
        // Add a domain
        $response = $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );
        $this->isFailure($response);
    }

    public function testDelete()
    {
        // First we need a ledger and domain
        $this->createLedger();

        // Add a domain
        $response = $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );
        $addResult = $this->isSuccessful($response);

        // Now delete it
        $requestData = [
            'code' => 'ENG',
            'revision' => $addResult->domain->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/delete', $requestData
        );
        $actual = $this->isSuccessful($response, 'success');
        // Check the response against our schema
        $this->validateResponse($actual, 'domain-response');

        // Confirm that a fetch fails
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $this->isFailure($response);
    }

    public function testGet()
    {
        // First we need a ledger
        $this->createLedger();

        // Now fetch the default domain
        $requestData = [
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->hasAttributes(
            ['code', 'currency', 'names'],
            $actual->domain
        );
        $this->hasRevisionElements($actual->domain);
        $this->assertEquals('CORP', $actual->domain->code);
        $this->assertEquals('CAD', $actual->domain->currency);

        // Expect error with invalid code
        $requestData = ['code' => 'bob'];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
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
        $this->createDomains();

        // Query for everything, paginated
        $requestData = [
            'limit' => 20,
        ];
        [$pages, $totalAccounts] = $this->getPagedDomains($requestData);
        $actualAccounts = LedgerDomain::count();
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
        $this->createDomains();

        // Query for journals containing 9, paginated
        $requestData = [
            'limit' => 10,
            'names' => [
                [
                    'name' => 'Domain 5'
                ],
                [
                    'name' => 'Domain 6',
                    'language' => 'en'
                ],
                [
                    'name' => '%ain 1%',
                    'like' => true,
                ],
                [
                    'name' => '%15%',
                    'exclude' => true,
                    'like' => true,
                ],
            ],
        ];
        [$pages, $totalAccounts] = $this->getPagedDomains($requestData);

        // Add 2 for the two single domain codes.
        $actualAccounts = 12;
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($actualAccounts, $totalAccounts);
        $this->assertEquals($expectedPages, $pages);
    }

    /**
     * @throws Breaker
     */
    public function testQueryRange()
    {
        // First we need a ledger
        $this->createLedger();

        // Add some test domains
        $this->createDomains();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 3,
            'range' => 'D10',
            'rangeEnding' => 'D19',
        ];
        [$pages, $totalAccounts] = $this->getPagedDomains($requestData);
        $actualAccounts = LedgerDomain::whereBetween('code', ['D10', 'D19'])
            ->count();
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
        $this->createDomains();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 5,
            'rangeEnding' => 'D19',
        ];
        [$pages, $totalAccounts] = $this->getPagedDomains($requestData);
        $actualAccounts = LedgerDomain::where('code', '<=', 'D19')
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
        $this->createDomains();

        // Query for a closed range, paginated
        $requestData = [
            'limit' => 10,
            'range' => 'D60',
        ];
        [$pages, $totalAccounts] = $this->getPagedDomains($requestData);
        $actualAccounts = LedgerDomain::where('code', '>=', 'D60')
            ->count();
        $expectedPages = (int)ceil(($actualAccounts + 1) / $requestData['limit']);
        $this->assertEquals($expectedPages, $pages);
        $this->assertEquals($actualAccounts, $totalAccounts);
    }

    /**
     * TODO: create a separate suite for updates where transactions present.
     * @throws Breaker
     */
    public function testUpdate()
    {
        // First we need a ledger
        $this->createLedger();

        // Verify the default domain is as expected
        $rules = LedgerAccount::rules();
        $this->assertEquals('CORP', $rules->domain->default);

        // Try an update with bogus data
        $requestData = [
            'revision' => 'bogus',
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $this->isFailure($response);

        // Do a get so we have a valid revision
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Now try with a valid revision
        $requestData = [
            'revision' => $actual->domain->revision,
            'code' => 'Corp',
            'toCode' => 'Main' // Expect conversion to uppercase
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $result = $this->isSuccessful($response);
        $this->assertEquals(Create::DEFAULT_DOMAIN, $result->domain->code);
        $this->assertEquals('CAD', $result->domain->currency);

        // Attempt a retry with the same (now invalid) revision.
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $this->isFailure($response);

        // Make sure the default domain has been updated
        $rules = LedgerAccount::rules();
        $this->assertEquals(Create::DEFAULT_DOMAIN, $rules->domain->default);
    }

    public function testUpdateBadName()
    {
        //Create a ledger
        $this->createLedger();

        // Add a domain
        $this->json(
            'post', 'api/ledger/domain/add', $this->baseRequest
        );

        // Add a new domain
        $badRequest = [
            'code' => 'DUP',
            'names' => [
                [
                    'name' => 'This is ok',
                    'language' => 'en'
                ],
                [
                    'name' => 'Misbehaving Nerds',
                    'language' => 'en-JOCK'
                ],
            ],
            'currency' => 'CAD'
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/add', $badRequest
        );
        $actual = $this->isSuccessful($response);

        // Now try to set the en-JOCK name of the second domain to that of the first
        $requestData = [
            'revision' => $actual->domain->revision,
            'code' => 'DUP',
            'names' => [
                [
                    'name' => 'Nerds',
                    'language' => 'en-JOCK'
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $this->isFailure($response);

    }

    public function testUpdateNameDelete()
    {
        // First we need a ledger
        $this->createLedger();

        // Do a get so we have a valid revision
        $requestData = [
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Remove the en-JOCK translation
        $requestData = [
            'revision' => $actual->domain->revision,
            'code' => 'Corp',
            'names' => [
                [
                    'language' => 'en-JOCK'
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $result = $this->isSuccessful($response);
        // Check the database
        $name = LedgerName::where('ownerUuid', $result->domain->uuid)
            ->where('language', 'en-JOCK')->first();
        $this->assertNull($name);

    }

    public function testUpdateNameDeleteAll()
    {
        // First we need a ledger
        $this->createLedger();

        // Do a get so we have a valid revision
        $requestData = [
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Attempt to remove all the names.
        $requestData = [
            'revision' => $actual->domain->revision,
            'code' => 'Corp',
            'names' => [
                [
                    'language' => 'en-CA'
                ],
            ],
        ];
        // Deleting one should work
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $this->isSuccessful($response);
        $requestData['names'][0]['language'] = 'fr-CA';
        // But removing the last one should fail
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $this->isFailure($response);

    }

    public function testUpdateNameEdit()
    {
        // First we need a ledger
        $this->createLedger();

        // Do a get so we have a valid revision
        $requestData = [
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response);

        // Fix the French translation
        $requestData = [
            'revision' => $actual->domain->revision,
            'code' => 'Corp',
            'names' => [
                [
                    'name' => 'ingénierie',
                    'language' => 'fr'
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/update', $requestData
        );
        $result = $this->isSuccessful($response);
        // Check the database
        $name = LedgerName::where('ownerUuid', $result->domain->uuid)
            ->where('language', 'fr')->first();
        $this->assertEquals('ingénierie', $name->name);

    }

}
