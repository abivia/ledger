<?php

namespace Abivia\Ledger\Reports;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Illuminate\Support\Facades\DB;

class TrialBalanceReport extends AbstractReport
{

    /**
     * Get the raw data required to generate the report.
     *
     * @throws Breaker
     */
    public function collect(Report $message)
    {
        /** @var LedgerDomain $ledgerDomain */
        $ledgerDomain = LedgerDomain::findWith($message->domain)->first();
        if ($ledgerDomain === null) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                __('Domain :code not found.', ['code' => $message->domain->code])
            );
        }
        // Get the balance changes for each account between the report date and now.
        $ledgerCurrency = LedgerCurrency::findOrBreaker($message->currency);
        $cast = 'decimal(' . LedgerCurrency::AMOUNT_SIZE . ",$ledgerCurrency->decimals)";
        $entryTable = (new JournalEntry)->getTable();
        $detailTable = (new JournalDetail)->getTable();
        $balanceChanges = DB::table($detailTable)
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
            ->where('posted', true)
            ->orderBy("$detailTable.ledgerUuid")
            ->get()
            ->keyBy('uuid')
        ;

        // Get balances for all accounts in the ledger
        $accountTable = (new LedgerAccount)->getTable();
        $balanceTable = (new LedgerBalance)->getTable();
        $balances = DB::table($accountTable)
            ->select()
            ->leftJoin($balanceTable, "$accountTable.ledgerUuid",
                '=', "$balanceTable.ledgerUuid"
            )
            ->where('domainUuid', $ledgerDomain->domainUuid)
            ->where('currency', $message->currency)
            ->get()
            ->keyBy('ledgerUuid');
        // Subtract them from the current balance
        // select ledgeraccount, parent where domain=id and currecny=request left join balances
        // foreach changedBalance ledgerbalance = ledgerbalance - sumFromFirstQuery
        // NOTE: MUST CHECK FOR NULL IN BALANCE CHANGES
        $dummy = 1;
        // Return the resulting account values, with tree links
        // Rebuild a collection using external account codes, sort, add in options, return.
    }

    public function prepare(Report $message, $reportData)
    {
        // TODO: Implement prepare() method.
    }
}
