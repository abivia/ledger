<?php /** @noinspection PhpParamsInspection */

namespace Abivia\Ledger\Tests\Feature;

use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\JournalReference;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Tests\TestCaseWithMigrations;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test Ledger API calls that don't involve journal transactions.
 */
class JournalEntryTest extends TestCaseWithMigrations
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'entry';
    }

    protected function addAccount(string $code, string $parentCode)
    {
        // Add an account
        $requestData = [
            'code' => $code,
            'parent' => [
                'code' => $parentCode,
            ],
            'names' => [
                [
                    'name' => "Account $code with parent $parentCode",
                    'language' => 'en',
                ]
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/account/add', $requestData
        );

        return $this->isSuccessful($response, 'account');
    }

    protected function addSalesTransaction() {
        // Add a transaction, sales to A/R
        $requestData = [
            'currency' => 'CAD',
            'description' => 'Sold the first thing!',
            'date' => '2021-11-12',
            'details' => [
                [
                    'code' => '1310',
                    'debit' => '520.71'
                ],
                [
                    'code' => '4110',
                    'credit' => '520.71'
                ],
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/add', $requestData
        );

        return [$requestData, $response];
    }

    protected function addSplitTransaction() {
        // Add a transaction, sales to A/R
        $requestData = [
            'currency' => 'CAD',
            'description' => 'Got paid for the first thing!',
            'date' => '2021-11-12',
            'details' => [
                [
                    'code' => '4110',
                    'amount' => '-520.71'
                ],
                [
                    'code' => '1120',
                    'amount' => '500.60'
                ],
                [
                    'code' => '2250',
                    'amount' => '20.11'
                ],
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/add', $requestData
        );

        return [$requestData, $response];
    }

    /**
     * Create a valid ledger
     *
     * @return void
     * @throws \Exception
     */
    public function testCreate(): void
    {
        $response = $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        $this->isSuccessful($response, 'ledger');

        //$this->dumpLedger();
    }

    public function testAdd()
    {
        // First we need a ledger
        $response = $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        $this->isSuccessful($response, 'ledger');

        [$requestData, $response] = $this->addSalesTransaction();
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->entry);

        // Check that we really did do everything that was supposed to be done.
        $journalEntry = JournalEntry::find($actual->entry->id);
        $this->assertNotNull($journalEntry);
        $this->assertTrue(
            $journalEntry->transDate->equalTo(new Carbon($requestData['date']))
        );
        $this->assertEquals('CAD', $journalEntry->currency);
        $this->assertEquals($requestData['description'], $journalEntry->description);
        $ledgerDomain = LedgerDomain::find($journalEntry->domainUuid);
        $this->assertNotNull($ledgerDomain);
        $this->assertEquals('CORP', $ledgerDomain->code);

        /** @var JournalDetail $detail */
        foreach ($journalEntry->details as $detail) {
            $ledgerAccount = LedgerAccount::find($detail->ledgerUuid);
            $this->assertNotNull($ledgerAccount);
            $ledgerBalance = LedgerBalance::where([
                    ['ledgerUuid', '=', $detail->ledgerUuid],
                    ['domainUuid', '=', $ledgerDomain->domainUuid],
                    ['currency', '=', $journalEntry->currency]]
            )->first();
            $this->assertNotNull($ledgerBalance);
            if ($ledgerAccount->code === '1310') {
                $this->assertEquals('-520.71', $ledgerBalance->balance);
            } else {
                $this->assertEquals('520.71', $ledgerBalance->balance);
            }
        }
    }

    public function testAddSplit()
    {
        // First we need a ledger
        $response = $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        $this->isSuccessful($response, 'ledger');

        $this->addSalesTransaction();
        [$requestData, $response] = $this->addSplitTransaction();
        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->entry);

        // Check that we really did do everything that was supposed to be done.
        $journalEntry = JournalEntry::find($actual->entry->id);
        $this->assertNotNull($journalEntry);
        $this->assertTrue(
            $journalEntry->transDate->equalTo(new Carbon($requestData['date']))
        );
        $this->assertEquals('CAD', $journalEntry->currency);
        $this->assertEquals($requestData['description'], $journalEntry->description);
        $ledgerDomain = LedgerDomain::find($journalEntry->domainUuid);
        $this->assertNotNull($ledgerDomain);
        $this->assertEquals('CORP', $ledgerDomain->code);

        $expectByCode = [
            '1310' => '-520.71',
            '4110' => '0.00',
            '1120' => '500.60',
            '2250' => '20.11',
        ];
        // Check all balances in the ledger
        foreach (LedgerAccount::all() as $ledgerAccount) {
            /** @var LedgerBalance $ledgerBalance */
            foreach ($ledgerAccount->balances as $ledgerBalance) {
                $this->assertEquals('CAD', $ledgerBalance->currency);
                $this->assertEquals(
                    $expectByCode[$ledgerAccount->code],
                    $ledgerBalance->balance,
                    "Code $ledgerAccount->code"
                );
                unset($expectByCode[$ledgerAccount->code]);
            }
        }
        $this->assertCount(0, $expectByCode);
    }

    public function testAddSplitWithReference()
    {
        // First we need a ledger
        $response = $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        $this->isSuccessful($response, 'ledger');

        // Add a reference
        $ref = new JournalReference();
        $ref->code = 'cust1';
        $domain = LedgerDomain::first();
        $ref->domainUuid = $domain->domainUuid;
        $ref->save();
        $ref->refresh();

        // Add a split with the reference
        $requestData = [
            'currency' => 'CAD',
            'description' => 'Got paid for the first thing!',
            'date' => '2021-11-12',
            'details' => [
                [
                    'code' => '4110',
                    'amount' => '-520.00',
                ],
                [
                    'code' => '1120',
                    'amount' => '500.00',
                    'reference' => [
                        'code' => 'cust1',
                    ],
                ],
                [
                    'code' => '2250',
                    'amount' => '20.00',
                ],
            ]
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/add', $requestData
        );

        $actual = $this->isSuccessful($response);
        $this->hasRevisionElements($actual->entry);

        // Check that the reference was stored successfully.
        $journalEntry = JournalEntry::find($actual->entry->id);
        $this->assertNotNull($journalEntry);
        $detail = $journalEntry->details()->skip(1)->first();
        $this->assertEquals($ref->journalReferenceUuid, $detail->journalReferenceUuid);

    }

    public function testDelete()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $response] = $this->addSalesTransaction();
        $actual = $this->isSuccessful($response);

        // Get the created data
        $journalEntry = JournalEntry::find($actual->entry->id);
        $this->assertNotNull($journalEntry);
        $details = $journalEntry->details;
        $ledgerDomain = LedgerDomain::find($journalEntry->domainUuid);

        // Now delete the entry
        $deleteData = [
            'id' => $actual->entry->id,
            'revision' => $actual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/delete', $deleteData
        );
        $this->isSuccessful($response, 'success');

        // Confirm that records are deleted and balances corrected.
        $journalEntryDeleted = JournalEntry::find($actual->entry->id);
        $this->assertNull($journalEntryDeleted);
        // Check journal detail records deleted
        $this->assertEquals(
            0,
            JournalDetail::where('journalEntryId', $actual->entry->id)
                ->count()
        );
        foreach ($details as $detail) {
            $ledgerAccount = LedgerAccount::find($detail->ledgerUuid);
            $this->assertNotNull($ledgerAccount);
            $ledgerBalance = LedgerBalance::where([
                    ['ledgerUuid', '=', $detail->ledgerUuid],
                    ['domainUuid', '=', $ledgerDomain->domainUuid],
                    ['currency', '=', $journalEntry->currency]]
            )->first();
            $this->assertNotNull($ledgerBalance);
            $this->assertEquals('0.00', $ledgerBalance->balance);
        }
    }

    public function testDeleteLocked()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $response] = $this->addSalesTransaction();
        $actual = $this->isSuccessful($response);

        // Get the created data
        $journalEntry = JournalEntry::find($actual->entry->id);
        $this->assertNotNull($journalEntry);

        // Lock the entry
        $lockData = [
            'id' => $actual->entry->id,
            'lock' => true,
            'revision' => $actual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/lock', $lockData
        );
        $actual = $this->isSuccessful($response);

        // Attempt to delete the entry
        $deleteData = [
            'id' => $actual->entry->id,
            'revision' => $actual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/delete', $deleteData
        );
        $this->isFailure($response);

        // Unlock the entry
        $lockData = [
            'id' => $actual->entry->id,
            'lock' => false,
            'revision' => $actual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/lock', $lockData
        );
        $actual = $this->isSuccessful($response);

        // Try to delete the entry again
        $deleteData = [
            'id' => $actual->entry->id,
            'revision' => $actual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/delete', $deleteData
        );
        $this->isSuccessful($response, 'success');
    }

    public function testGet()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $addResponse] = $this->addSalesTransaction();
        $addActual = $this->isSuccessful($addResponse);

        // Get the created data by ID
        $fetchData = [
            'id' => $addActual->entry->id
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/get', $fetchData
        );
        $fetched = $this->isSuccessful($response);
        $this->hasRevisionElements($fetched->entry);

        // Verify the contents
        $entry = $fetched->entry;
        $this->assertEquals($addActual->entry->id, $entry->id);
        $date = new Carbon($entry->date);
        $this->assertTrue(
            $date->equalTo(new Carbon($requestData['date']))
        );
        $this->assertEquals('CAD', $entry->currency);
        $this->assertEquals($requestData['description'], $entry->description);
        $expectDetails = [
            '1310' => '-520.71',
            '4110' => '520.71',
        ];
        foreach ($entry->details as $detail) {
            $this->assertArrayHasKey($detail->accountCode, $expectDetails);
            $this->assertEquals($expectDetails[$detail->accountCode], $detail->amount);
        }
    }

    public function testGetLocked()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $addResponse] = $this->addSalesTransaction();
        $addActual = $this->isSuccessful($addResponse);

        // Lock the entry
        $lockData = [
            'id' => $addActual->entry->id,
            'lock' => true,
            'revision' => $addActual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/lock', $lockData
        );
        $actual = $this->isSuccessful($response);

        // Expect that a get request still works
        $fetchData = [
            'id' => $addActual->entry->id
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/get', $fetchData
        );
        $this->isSuccessful($response);
    }

    public function testLockBad()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $addResponse] = $this->addSalesTransaction();
        $addActual = $this->isSuccessful($addResponse);

        // Send a bad lock request with no flag
        $lockData = [
            'id' => $addActual->entry->id,
            'revision' => $addActual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/lock', $lockData
        );
        $this->isFailure($response);

    }

    public function testUpdate()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $addResponse] = $this->addSalesTransaction();
        $addActual = $this->isSuccessful($addResponse);

        // Update the transaction
        $requestData['id'] = $addActual->entry->id;
        $requestData['revision'] = $addActual->entry->revision;
        $requestData['description'] = 'Oops, that was a rental!';
        $requestData['details'][1]['code'] = '4240';
        $response = $this->json(
            'post', 'api/ledger/entry/update', $requestData
        );
        $result = $this->isSuccessful($response);

        // Check that we really did do everything that was supposed to be done.
        $journalEntry = JournalEntry::find($addActual->entry->id);
        $this->assertNotNull($journalEntry);
        $bob = $journalEntry->transDate;
        $this->assertTrue(
            $journalEntry->transDate->equalTo(new Carbon($requestData['date']))
        );
        $this->assertEquals('CAD', $journalEntry->currency);
        $this->assertEquals($requestData['description'], $journalEntry->description);
        $ledgerDomain = LedgerDomain::find($journalEntry->domainUuid);
        $this->assertNotNull($ledgerDomain);
        $this->assertEquals('CORP', $ledgerDomain->code);

        $expectByCode = [
            '1310' => '-520.71',
            '4110' => '0.00',
            '4240' => '520.71',
        ];
        // Check all balances in the ledger
        foreach (LedgerAccount::all() as $ledgerAccount) {
            /** @var LedgerBalance $ledgerBalance */
            foreach ($ledgerAccount->balances as $ledgerBalance) {
                $this->assertEquals('CAD', $ledgerBalance->currency);
                $this->assertEquals(
                    $expectByCode[$ledgerAccount->code],
                    $ledgerBalance->balance,
                    "For {$ledgerAccount->code}"
                );
                unset($expectByCode[$ledgerAccount->code]);
            }
        }
        $this->assertCount(0, $expectByCode);
    }

    public function testUpdateLocked()
    {
        // First we need a ledger and transaction
        $this->createLedger(['template'], ['template' => 'manufacturer_1.0']);

        [$requestData, $addResponse] = $this->addSalesTransaction();
        $addActual = $this->isSuccessful($addResponse);

        // Lock the entry
        $lockData = [
            'id' => $addActual->entry->id,
            'lock' => true,
            'revision' => $addActual->entry->revision,
        ];
        $response = $this->json(
            'post', 'api/ledger/entry/lock', $lockData
        );
        $actual = $this->isSuccessful($response);

        // Expect an update to fail.
        $requestData['id'] = $addActual->entry->id;
        $requestData['revision'] = $actual->entry->revision;
        $requestData['description'] = 'Oops, that was a rental!';
        $requestData['details'][1]['code'] = '4240';
        $response = $this->json(
            'post', 'api/ledger/entry/update', $requestData
        );
        $this->isFailure($response);
    }

}
