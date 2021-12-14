<?php

namespace Abivia\Ledger\Tests\Feature\Reports;

use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\ReportAccount;
use Abivia\Ledger\Models\ReportData;
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

    private function getRequest(): Report
    {
        $request = new Report();
        $request->name = 'trialBalance';
        $request->currency = 'CAD';
        $request->toDate = new Carbon('2001-02-28');

        return $request;
    }

    private function loadRandomBaseline()
    {
        // Create a ledger and a known set of transactions.
        $sql = file_get_contents(
            __DIR__ . '/../../Seeders/random_baseline.sql'
        );
        foreach (explode('-- Table', $sql) as $statement) {
            DB::statement(trim($statement));
        }
    }

    public function setUp(): void
    {
        parent::setUp();
        self::$expectContent = 'TBD';
    }

    public function testCollect()
    {
        $this->loadRandomBaseline();

        $request = $this->getRequest();
        $report = new TrialBalanceReport();
        $reportData = $report->collect($request);
        $this->assertCount(139, $reportData->accounts);
        return $reportData;
    }

    /**
     * @depends testCollect
     * @return void
     */
    public function testPrepare(ReportData $reportData)
    {
        $this->loadRandomBaseline();
        $report = new TrialBalanceReport();
        $prepared = $report->prepare($reportData);
        $this->assertCount(138, $prepared);
        $lines = [];
        /** @var ReportAccount $account */
        foreach ($prepared as $account) {
            $line = [$account->total, $account->balance, $account->creditBalance, $account->debitBalance];
            $line[] = '"' . $account->name . '"';
            $line[] = $account->code;

            $lines[] = implode(',', array_reverse($line)) . "\n";
        }
        // See if we can/should log this
        $exportPath = __DIR__ . '/../../../local';
        if (is_dir($exportPath)) {
            $exportPath = realpath("$exportPath/trial.csv");
            file_put_contents($exportPath, implode($lines));
        }
        $this->assertTrue(true);

    }
}
