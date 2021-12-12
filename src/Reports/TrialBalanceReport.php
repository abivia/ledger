<?php

namespace Abivia\Ledger\Reports;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Messages\ReportAccount;
use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

class TrialBalanceReport extends AbstractReport
{
    private Collection $accounts;
    /**
     * @var string[][]
     */
    private array $byParent;
    private int $decimals;
    /**
     * @var mixed
     */
    private array $languages;

    /**
     * Get the raw data required to generate the report.
     *
     * @throws Breaker
     * @throws Exception
     */
    public function collect(Report $message): stdClass
    {
        /** @var LedgerDomain $ledgerDomain */
        $ledgerDomain = LedgerDomain::findWith($message->domain)->first();
        if ($ledgerDomain === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Domain :code not found.', ['code' => $message->domain->code])
            );
        }
        $reportData = new stdClass();
        $reportData->message = $message;
        $reportData->journalEntryId = JournalEntry::query()->max('journalEntryId');

        // Grab the table names
        $accountTable = (new LedgerAccount)->getTable();
        $balanceTable = (new LedgerBalance)->getTable();
        $detailTable = (new JournalDetail)->getTable();
        $entryTable = (new JournalEntry)->getTable();

        // Get the balance changes for each account between the report date and now.
        $ledgerCurrency = LedgerCurrency::findOrBreaker($message->currency);
        $reportData->decimals = $ledgerCurrency->decimals;
        $cast = 'decimal(' . LedgerCurrency::AMOUNT_SIZE . ", $ledgerCurrency->decimals)";
        $balanceChangeQuery = DB::table($detailTable)
            ->select(DB::raw(
                "`$detailTable`.`ledgerUuid` AS `uuid`,"
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

        // Get balances for all accounts in the ledger with this currency
        $balanceQuery = DB::table($accountTable)
            ->select()
            ->leftJoin($balanceTable, "$accountTable.ledgerUuid",
                '=', "$balanceTable.ledgerUuid"
            )
            ->where('domainUuid', $ledgerDomain->domainUuid)
            ->where('currency', $message->currency)
            ->orderBy('code');
        //$bSql = $balanceQuery->toSql();
        $balances = $balanceQuery->get()->keyBy('ledgerUuid');

        // Subtract balance changes from the current balance
        $reportData->accounts = [];
        $zero = bcadd('0', '0', $ledgerCurrency->decimals);
        foreach ($balances as $uuid => $balance) {
            // Zero in accounts with no balance.
            $balance->balance ??= $zero;

            // Create a report account and adjust the balance.
            $account = ReportAccount::fromArray((array)$balance, Message::OP_ADD);
            $account->parent = $balances[$balance->parentUuid]->code ?? '';
            if (isset($balanceChanges[$uuid])) {
                $account->balance = bcsub(
                    $account->balance,
                    $balanceChanges[$uuid]->delta,
                    $ledgerCurrency->decimals
                );
            }
            $reportData->accounts[$account->code] = $account;
        }

        return $reportData;
    }

    public function prepare(Report $message, $reportData): Collection
    {
        $this->accounts = collect($reportData->accounts)->keyBy('code');
        $this->decimals = $reportData->decimals;
        $this->rollUp();

        $format = $message->options['format'] ?? '';
        if ($format === 'raw') {
            return $this->accounts;
        }
        $this->languages = $message->options['language'];

        // Do a simple report
        /**
         * @var string $code
         * @var ReportAccount $account
         */
        foreach ($this->accounts as $code => $account) {
            $ledgerAccount = LedgerAccount::find($account->ledgerUuid);
            unset($account->name);
            $names = $ledgerAccount->names->keyBy('language');
            foreach ($this->languages as $language) {
                if (isset($names[$language])) {
                    $account->name = $names[$language]->name;
                }
            }
            if (!isset($account->name)) {
                $account->name = $names->first()->name;
            }
            $account->setReportTotals($this->decimals);
        }

        return $this->accounts;
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
            $this->byParent[$account->parent][] = $code;
        }

        // Add a temporary account for the root.
        $root = new ReportAccount();
        $root->code = '';
        $root->balance = '0';
        $root->total = '0';
        $this->accounts->put('', $root);

        // Roll the balances up
        $this->rollUpAccount('', 1);

        // Integrity check: the root account balance must still be zero
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
     * @param string $code
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
