<?php

namespace Abivia\Ledger\Tests\Feature\Reports;

use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Reports\TrialBalanceReport;
use Abivia\Ledger\Tests\Feature\CommonChecks;
use Abivia\Ledger\Tests\Feature\CreateLedgerTrait;
use Abivia\Ledger\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrialBalanceReportTest extends TestCase
{
    use CommonChecks;
    use CreateLedgerTrait;
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'TBD';
    }

    public function testCollect()
    {
        // Create a ledger and a set of transactions.
        $this->createLedger(
            ['template', 'date'],
            ['template' => 'manufacturer', 'date' => '2021-12-04']
        );
        $this->addRandomTransactions(15);

        $request = new Report();
        $request->name = 'trialBalance';
        $request->currency = 'CAD';
        $request->toDate = Carbon::tomorrow();
        $request->validate(0);
        $report = new TrialBalanceReport();
        $report->collect($request);
        $this->assertTrue(true);
    }

    public function testPrepare()
    {

    }
}
