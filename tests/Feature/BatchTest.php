<?php
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
/** @noinspection PhpParamsInspection */
declare(strict_types=1);

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Abivia\Ledger\Tests\ValidatesJson;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Batch API calls.
 */
class BatchTest extends TestCaseWithMigrations
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

    public function setUp(): void
    {
        parent::setUp();
        LedgerAccount::resetRules();
        self::$expectContent = 'batch';
    }

    public function testCreateFails()
    {
        // Attempt a revision with a nonexistent reference
        $requestData = [
            'list' => [
                [
                    'method' => 'root/create',
                    'payload' => [],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $this->isFailure($response);
    }

    public function testCreateFailsOnExistingLedger()
    {
        // First we need a ledger
        $this->createLedger();

        // Attempt a revision with a nonexistent reference
        $requestData = [
            'list' => [
                [
                    'method' => 'root/create',
                    'payload' => [],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $this->isFailure($response);
    }

    public function testEntryAccount()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'account/get',
                    'payload' => [
                        'code' => '1010',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isFailure($response);
        $this->assertEquals('Account invalid or not found.', $actual->errors[0]);
    }

    public function testEntryBalance()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'balance/get',
                    'payload' => [
                        'code' => '1120',
                        'currency' => 'CAD',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isFailure($response);
        $this->assertEquals('Account 1120 not found.', $actual->errors[1]);
    }

    public function testEntryCurrency()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'currency/get',
                    'payload' => [
                        'code' => 'USD',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isFailure($response);
        $this->assertEquals('currency USD does not exist', $actual->errors[1]);
    }

    public function testEntryCurrencyQuery()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'currency/query',
                    'payload' => [],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
    }

    public function testEntryDomain()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'domain/get',
                    'payload' => [
                        'code' => 'CORP',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
    }

    public function testEntryEntry()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'entry/get',
                    'payload' => [
                        'id' => 123456,
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isFailure($response);
        $this->assertEquals('Journal entry 123456 does not exist', $actual->errors[1]);
    }

    public function testEntryEntryQuery()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'entry/query',
                    'payload' => [],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
    }

    public function testEntryDomainQuery()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'domain/query',
                    'payload' => [],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
    }

    public function testEntryJournal()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'journal/get',
                    'payload' => [
                        'code' => 'TECH',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isFailure($response);
        $this->assertEquals('Journal TECH does not exist', $actual->errors[1]);
    }

    public function testEntryJournalQuery()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'journal/query',
                    'payload' => [],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
    }

    public function testEntryReference()
    {
        // First we need a ledger
        $this->createLedger();

        // Create a batch that fetches every batchabel type of item
        // (the items need not exist).
        $requestData = [
            'list' => [
                [
                    'method' => 'reference/get',
                    'payload' => [
                        'code' => 'abcd',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isFailure($response);
        $this->assertEquals('domain abcd does not exist in domain CORP', $actual->errors[1]);
    }

    public function testLimit()
    {
        // First we need a ledger and a batch limit
        $this->createLedger();
        LedgerAccount::setRules(
            (object)['batch' => (object)['limit' => 2]]
        );

        // Create a batch with a fetch and three revisions
        $requestData = [
            'list' => [
                [
                    'method' => 'domain/get',
                    'payload' => [
                        'code' => 'CORP',
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'CORP',
                        'toCode' => 'MAIN'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'MAIN',
                        'toCode' => 'NEXT'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'NEXT',
                        'toCode' => 'LAST'
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $this->isFailure($response);
    }

    public function testReport()
    {
        // First we need a ledger and a batch limit
        $this->createLedger();

        // Create a batch with a report request
        $requestData = [
            'list' => [
                [
                    'method' => 'report',
                    'payload' => [
                        'name' => 'trialBalance',
                        'currency' => 'CAD',
                        'toDate' => '2001-02-28',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $this->isSuccessful($response);
    }

    public function testReportProhibition()
    {
        // First we need a ledger and a batch limit
        $this->createLedger();
        LedgerAccount::setRules(
            (object)['batch' => (object)['allowReports' => false]]
        );

        // Create a batch with a report request
        $requestData = [
            'list' => [
                [
                    'method' => 'report',
                    'payload' => [
                        'name' => 'trialBalance',
                        'currency' => 'CAD',
                        'toDate' => '2001-02-28',
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $this->isFailure($response);
    }

    /**
     * @throws Breaker
     */
    public function testRevisionEmbedded()
    {
        // First we need a ledger
        $this->createLedger();

        // Verify the default domain is as expected
        $rules = LedgerAccount::rules();
        $this->assertEquals('CORP', $rules->domain->default);

        // Create a batch with a fetch and three revisions
        $requestData = [
            'list' => [
                [
                    'method' => 'domain/get',
                    'payload' => [
                        'code' => 'CORP',
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'CORP',
                        'toCode' => 'MAIN'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'MAIN',
                        'toCode' => 'NEXT'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'revision' => '&',
                        'code' => 'NEXT',
                        'toCode' => 'LAST'
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(4, $actual->batch);

        // Make sure the last domain exists
        $requestData = [
            'code' => 'LAST',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response, 'domain');
    }

    /**
     * @throws Breaker
     */
    public function testRevisionMissing()
    {
        // First we need a ledger
        $this->createLedger();

        // Verify the default domain is as expected
        $rules = LedgerAccount::rules();
        $this->assertEquals('CORP', $rules->domain->default);

        // Attempt a revision with a nonexistent reference
        $requestData = [
            'list' => [
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'CORP',
                        'toCode' => 'MAIN'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'code' => 'MAIN',
                        'toCode' => 'NEXT'
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $this->isFailure($response);
    }

    /**
     * @throws Breaker
     */
    public function testRevisionPreset()
    {
        // First we need a ledger
        $this->createLedger();

        // Verify the default domain is as expected
        $rules = LedgerAccount::rules();
        $this->assertEquals('CORP', $rules->domain->default);

        // Do a get so we have a valid revision
        $requestData = [
            'code' => 'Corp',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response, 'domain');

        // Now create a batch with three revisions
        $requestData = [
            'list' => [
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'revision' => $actual->domain->revision,
                        'code' => 'CORP',
                        'toCode' => 'MAIN'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'revision' => $actual->domain->revision,
                        'code' => 'MAIN',
                        'toCode' => 'NEXT'
                    ],
                ],
                [
                    'method' => 'domain/update',
                    'payload' => [
                        'revision' => $actual->domain->revision,
                        'code' => 'NEXT',
                        'toCode' => 'LAST'
                    ],
                ],
            ],
        ];
        $response = $this->json(
            'post', 'api/ledger/batch', $requestData
        );
        $actual = $this->isSuccessful($response);
        $this->assertCount(3, $actual->batch);

        // Make sure the last domain exists
        $requestData = [
            'code' => 'LAST',
        ];
        $response = $this->json(
            'post', 'api/ledger/domain/get', $requestData
        );
        $actual = $this->isSuccessful($response, 'domain');
    }

}
