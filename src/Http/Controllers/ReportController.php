<?php

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerReport;
use Abivia\Ledger\Models\ReportAccount;
use Abivia\Ledger\Models\ReportData;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    private function cache(Report $message, $reportData)
    {
        LedgerReport::create(
            [
                'currency' => $message->currency,
                'domainUuid' =>$message->domain->uuid,
                'fromDate' => $message->fromDate ?? null,
                'journalEntryId' => $reportData->journalEntryId,
                'name' => $message->name,
                'reportData' => serialize($reportData),
                'toDate' => $message->toDate,
            ]
        );
    }

    /**
     * Load the data for this report and generate a response.
     * @param Report $message
     * @return Collection
     * @throws Breaker
     */
    public function generate(Report $message): Collection
    {
        $message->validate(0);
        if (!isset($message->domain->uuid)) {
            /** @var LedgerDomain $ledgerDomain */
            $ledgerDomain = LedgerDomain::findWith($message->domain)->first();
            if ($ledgerDomain === null) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST,
                    __('Domain :code is not defined.', ['code' => $message->domain->code])
                );
            }
            $message->domain->uuid = $ledgerDomain->domainUuid;
        }
        $className = 'Abivia\\Ledger\\Reports\\' . ucfirst($message->name) . 'Report';
        $reporter = new $className();
        $reportData = $this->getCached($message) ?? $reporter->collect($message);
        $this->cache($message, $reportData);

        return $reporter->prepare($reportData);
    }

    /**
     * Look for a cached report, verifying that the cache is current.
     * @param Report $message
     * @return ReportData|null
     */
    private function getCached(Report $message): ?ReportData
    {
        if ($message->options['force'] ?? false) {
            return null;
        }
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
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
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $entryQuery = JournalEntry::where('domainUuid', $message->domain->uuid)
                ->where('currency', $message->currency)
                ->where('journalEntryId', '>', $candidate->journalEntryId)
                ->where('transDate', '<=', $message->toDate);
            if (isset($message->fromDate)) {
                $entryQuery = $entryQuery->where('fromDate', '>=', $message->fromDate);
            }
            if ($entryQuery->count() == 0) {
                return unserialize(
                    $candidate->reportData,
                    [Report::class, ReportAccount::class, ReportData::class]
                );
            }
            // This report is outdated.
            $candidate->delete();
        }
        return null;
    }

}
