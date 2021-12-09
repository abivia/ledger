<?php

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerReport;

class ReportController extends Controller
{
    public function generate(Report $message)
    {
        $message->validate(0);
        $report = $this->getCached($message);
        if ($report === null) {
            $method = 'report' . ucfirst($message->name);
            $report = $this->$method($message);
            $this->cache($message, $report);
        }

        return $report;
    }

    private function getCached(Report $message)
    {
        $query = LedgerReport::where('name', $message->name)
            ->where('domainUuid', $message->domain->uuid)
            ->where('currency', $message->currency)
            ->where('toDate', $message->toDate);
        if (isset($message->fromDate)) {
            $query = $query->where('fromDate', $message->fromDate);
        }
        $candidates = $query->get();
        foreach ($candidates as $candidate) {
            // Look for any transactions made in the report period
            // made after this report was generated
            $entryQuery = JournalEntry::where('posted', true)
                ->where('domainUuid', $message->domain->uuid)
                ->where('currency', $message->currency)
                ->where('journalEntryId', '>', $candidate->journalEntryId)
                ->where('transDate', '<=', $message->toDate);
            if (isset($message->fromDate)) {
                $entryQuery = $entryQuery->where('fromDate', '>=', $message->fromDate);
            }
            if ($entryQuery->count() == 0) {
                return unserialize($candidate->reportData);
            }
            // This report is outdated.
            $candidate->delete();
        }
        return null;
    }

    public function reportTrialBalance(Report $message)
    {

    }

}
