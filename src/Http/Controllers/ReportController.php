<?php

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerReport;
use Abivia\Ledger\Models\ReportAccount;
use Abivia\Ledger\Models\ReportData;
use Abivia\Ledger\Reports\AbstractReport;
use Illuminate\Support\Collection;

class ReportController extends Controller
{
    private function cache(Report $message, $reportData)
    {
        LedgerReport::create(
            [
                'currency' => $message->currency,
                'domainUuid' => $message->domain->uuid,
                'fromDate' => $message->fromDate ?? null,
                'journalEntryId' => $reportData->journalEntryId,
                'name' => $message->name,
                'reportData' => serialize($reportData),
                'toDate' => $message->toDate,
            ]
        );
    }

    /**
     * Make reporter class from config or build by namespace
     * @param string $reportName
     * @return AbstractReport
     * @throws Breaker
     */
    protected function makeReporter(string $reportName): AbstractReport
    {
        $reporter = Report::getClass($reportName);
        if ($reporter === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Report `:name` not registered.', ['name' => $reportName])
            );
        }
        if (!class_exists($reporter)) {
            throw Breaker::withCode(
                Breaker::CONFIG_ERROR,
                __('Report `:name` is misconfigured.', ['name' => $reportName])
            );
        }
        $options = config('ledger.reportApiOptions');

        return new $reporter($options[$reportName] ?? []);
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
        if (!isset($message->domain->uuid) || !isset($message->currency)) {
            /** @var LedgerDomain $ledgerDomain */
            $ledgerDomain = LedgerDomain::findWith($message->domain)->first();
            if ($ledgerDomain === null) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST,
                    __('Domain :code is not defined.', ['code' => $message->domain->code])
                );
            }
            $message->domain->uuid = $ledgerDomain->domainUuid;
            if (!isset($message->currency)) {
                $message->currency = $ledgerDomain->currencyDefault;
            }
        }

        $reporter = $this->makeReporter($message->name);
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
                $entryQuery = $entryQuery->where('transDate', '>=', $message->fromDate);
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
