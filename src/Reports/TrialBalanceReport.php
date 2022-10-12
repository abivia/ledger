<?php

namespace Abivia\Ledger\Reports;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\ReportAccount;
use Abivia\Ledger\Models\ReportData;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TrialBalanceReport extends AbstractReport
{
    private Collection $accounts;
    /**
     * @var string[][]
     */
    private array $byParent;

    /**
     * @var int The number of decimal places in the requested currency
     */
    private int $decimals;

    /**
     * Get the raw data required to generate the report.
     *
     * @throws Breaker
     * @throws Exception
     */
    public function collect(Report $message): ReportData
    {
        $message->validate(0);
        /** @var LedgerDomain $ledgerDomain */
        $ledgerDomain = LedgerDomain::findWith($message->domain)->first();
        if ($ledgerDomain === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Domain :code not found.', ['code' => $message->domain->code])
            );
        }
        $reportData = new ReportData();
        $reportData->request = $message;
        $reportData->journalEntryId = JournalEntry::query()->max('journalEntryId');

        // Grab the table names
        $detailTable = (new JournalDetail)->getTable();
        $entryTable = (new JournalEntry)->getTable();
        $dbPrefix = DB::getTablePrefix();

        // Get the balance changes for each account between the report date and now.
        $ledgerCurrency = LedgerCurrency::findOrBreaker($message->currency);
        $reportData->decimals = $ledgerCurrency->decimals;
        $cast = 'decimal(' . LedgerCurrency::AMOUNT_SIZE . ", $ledgerCurrency->decimals)";
        $balanceChangeQuery = DB::table($detailTable)
            ->select(DB::raw(
                "`$dbPrefix$detailTable`.`ledgerUuid` AS `uuid`,"
                . " sum(cast(`amount` AS $cast)) AS `delta`")
            )
            ->join(
                $entryTable, "$detailTable.journalEntryId",
                '=', "$entryTable.journalEntryId"
            )
            ->where('domainUuid', $ledgerDomain->domainUuid)
            ->where('currency', $message->currency)
            ->where('transDate', '>', $message->toDate)
            ->groupBy("$detailTable.ledgerUuid")
            ->orderBy("$detailTable.ledgerUuid");
        //$bcSql = $balanceChangeQuery->toSql();
        $balanceChanges = $balanceChangeQuery->get()->keyBy('uuid');

        // Get a list of accounts
        $ledgerAccounts = LedgerAccount::all()->keyBy('code');
        // Get balances for all accounts in the ledger with this currency
        /** @var Collection $balances */
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $balances = LedgerBalance::where('domainUuid', $ledgerDomain->domainUuid)
            ->where('currency', $message->currency)
            ->get()
            ->keyBy('ledgerUuid');

        // Subtract balance changes from the current balance
        $reportData->accounts = [];
        $zero = bcadd('0', '0', $ledgerCurrency->decimals);
        foreach ($ledgerAccounts as $ledgerAccount) {
            $account = ReportAccount::fromArray($ledgerAccount->attributesToArray(), Message::OP_ADD);
            $uuid = $account->ledgerUuid;
            $account->currency = $message->currency;
            $account->balance = $balances->has($uuid)
                ? $balances->get($account->ledgerUuid)->balance : $zero;
            $parent = $ledgerAccounts->firstWhere('ledgerUuid', $ledgerAccount->parentUuid);
            $account->parent = $parent->code ?? '';
            if (isset($balanceChanges[$uuid])) {
                $account->balance = bcsub(
                    $account->balance,
                    $balanceChanges[$uuid]->delta,
                    $ledgerCurrency->decimals
                );
            }
            $reportData->accounts[$account->code] = $account;
        }
        ksort($reportData->accounts);

        return $reportData;
    }

    /**
     * Use a report's raw data to prepare report accounts.
     * @param ReportData $reportData
     * @return Collection
     * @throws Exception
     */
    public function prepare(ReportData $reportData): Collection
    {
        $result = collect(['request' => $reportData->request]);
        $this->accounts = collect($reportData->accounts)->keyBy('code');
        $this->decimals = $reportData->decimals;
        $this->rollUp();

        $format = $reportData->message->options['format'] ?? '';
        if ($format === 'raw') {
            return $this->accounts;
        }
        $languages = $reportData->request->options['language'];

        // Do a simple report
        /**
         * @var string $code
         * @var ReportAccount $account
         */
        foreach ($this->accounts as $account) {
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $ledgerAccount = LedgerAccount::find($account->ledgerUuid);
            unset($account->name);
            $names = $ledgerAccount->names->keyBy('language');
            foreach ($languages as $language) {
                if (isset($names[$language])) {
                    $account->name = $names[$language]->name;
                }
            }
            if (!isset($account->name)) {
                $account->name = $names->first()->name;
            }
            $account->setReportTotals($this->decimals);
        }
        $result->put('accounts', $this->accounts);

        return $result;
    }

    /**
     * Get totals of sub-accounts for each account, add to the balance.
     * @return void
     * @throws Exception
     */
    private function rollUp()
    {
        // Make a list of sub-accounts for each parent.
        $this->byParent = [];
        /**
         * @var string $code
         * @var ReportAccount $account
         */
        foreach($this->accounts as $code => $account) {
            $account->total = $account->balance;
            $this->byParent[$account->parent] ??= [];
            if ($code !== '') {
                $this->byParent[$account->parent][] = $code;
            }
        }

        // Roll the balances up
        $this->rollUpAccount('', 0);

        // Integrity check: the root account balance must still be zero
        $root = $this->accounts->get('');
        if (bccomp('0', $root->total, $this->decimals) !== 0) {
            $message = "Ledger net balance is $root->total";
            Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
                ->critical($message);
            throw new Exception($message);
        }
        $this->accounts->forget('');
    }

    /**
     * Recursively compute rolled up totals for an account.
     *
     * @param string $code Account code for the parent.
     * @param int $depth Levels of nesting below the ledger root.
     * @return void
     */
    private function rollUpAccount(string $code, int $depth)
    {
        $this->accounts[$code]->depth = $depth;
        if (isset($this->byParent[$code])) {
            $sum = '0';
            foreach ($this->byParent[$code] as $child) {
                $this->rollUpAccount($child, $depth + 1);
                $sum = bcadd($sum, $this->accounts[$child]->total, $this->decimals);
            }
            $this->accounts[$code]->total = bcadd(
                $this->accounts[$code]->balance, $sum, $this->decimals
            );
        }
    }
}
