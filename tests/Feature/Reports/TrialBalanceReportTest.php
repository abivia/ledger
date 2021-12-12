<?php

namespace Abivia\Ledger\Tests\Feature\Reports;

use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Messages\ReportAccount;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Reports\TrialBalanceReport;
use Abivia\Ledger\Tests\Feature\CommonChecks;
use Abivia\Ledger\Tests\Feature\CreateLedgerTrait;
use Abivia\Ledger\Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
        // Create a ledger and a known set of transactions.
        $sql = file_get_contents(
            __DIR__ . '/../../Seeders/random_baseline.sql'
        );
        foreach (explode('-- Table', $sql) as $statement) {
            DB::statement(trim($statement));
        }

        $request = new Report();
        $request->name = 'trialBalance';
        $request->currency = 'CAD';
        $request->toDate = new Carbon('2001-02-28');
        $request->validate(0);
        $report = new TrialBalanceReport();
        $reportData = $report->collect($request);
        $output = $report->prepare($request, $reportData);
        $lines = [];
        /** @var ReportAccount $account */
        foreach ($output as $account) {
            if ($account->depth > 3) {
                continue;
            }
            echo implode(
                ',',
                [$account->name, $account->debitTotal, $account->creditTotal, $account->depth]
                ) . "\n";
        }
        $this->assertTrue(true);
    }

    public function testPrepare()
    {

    }
}
