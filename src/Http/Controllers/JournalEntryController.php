<?php

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\SubJournal;
use Abivia\Ledger\Traits\Audited;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class JournalEntryController extends Controller
{
    use Audited;

    /**
     * @var LedgerCurrency|null The currency for this entry.
     */
    private ?LedgerCurrency $ledgerCurrency;

    /**
     * @var LedgerDomain|null The domain for this entry.
     */
    private ?LedgerDomain $ledgerDomain;

    /**
     * Add an entry to the journal.
     *
     * @param Entry $message
     * @return JournalEntry
     * @throws Breaker
     * @throws Exception
     */
    public function add(Entry $message): JournalEntry
    {
        $inTransaction = false;
        // Ensure that the entry is in balance and the contents are valid.
        $this->validateEntry($message, Message::OP_ADD);

        try {
            DB::beginTransaction();
            $inTransaction = true;
            // Store message basic details.
            $journalEntry = new JournalEntry();
            $journalEntry->fillFromMessage($message);
            $journalEntry->save();
            $journalEntry->refresh();
            // Create the detail records
            $this->addDetails($journalEntry, $message);
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $journalEntry;
    }

    /**
     * Write the journal detail records and update balances.
     *
     * @param JournalEntry $journalEntry
     * @param Entry $message
     * @return void
     */
    private function addDetails(JournalEntry $journalEntry, Entry $message)
    {
        foreach ($message->details as $detail) {
            $journalDetail = new JournalDetail();
            $journalDetail->journalEntryId = $journalEntry->journalEntryId;
            $journalDetail->ledgerUuid = $detail->account->uuid;
            $journalDetail->amount = $detail->amount;
            if (isset($detail->reference)) {
                $journalDetail->journalReferenceUuid = $detail->reference->journalReferenceUuid;
            }
            $journalDetail->save();
            // Create/adjust the ledger balances
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            $ledgerBalance = LedgerBalance::where([
                    ['ledgerUuid', '=', $journalDetail->ledgerUuid],
                    ['domainUuid', '=', $this->ledgerDomain->domainUuid],
                    ['currency', '=', $this->ledgerCurrency->code],
                ])
                ->first();

            if ($ledgerBalance === null) {
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                LedgerBalance::create([
                    'ledgerUuid' => $journalDetail->ledgerUuid,
                    'domainUuid' => $this->ledgerDomain->domainUuid,
                    'currency' => $this->ledgerCurrency->code,
                    'balance' => $journalDetail->amount,
                ]);
            } else {
                $ledgerBalance->balance = bcadd(
                    $ledgerBalance->balance,
                    $journalDetail->amount,
                    $this->ledgerCurrency->decimals
                );
                $ledgerBalance->save();
            }
        }
    }

    /**
     * Delete an entry and reverse balance changes.
     *
     * @param Entry $message
     * @throws Breaker
     */
    public function delete(Entry $message) {
        $inTransaction = false;
        // Ensure that the message contents are valid.
        $message->validate(Message::OP_DELETE);

        try {
            DB::beginTransaction();
            $inTransaction = true;
            // Get the Journal entry
            $journalEntry = $this->fetch($message->id);
            $journalEntry->checkRevision($message->revision ?? null);

            // We need the currency for balance adjustments.
            $this->getCurrency($journalEntry->currency);

            // Delete the detail records and update balances
            $this->deleteDetails($journalEntry);

            // Delete the journal entry
            $journalEntry->delete();
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }
    }

    /**
     * @param JournalEntry $journalEntry
     * @return void
     */
    private function deleteDetails(JournalEntry $journalEntry)
    {
        $journalDetails = JournalDetail::with(
            ['balances' => function ($query) use ($journalEntry) {
                $query->where('currency', $journalEntry->currency);
            }])
            ->where('journalEntryId', $journalEntry->journalEntryId)
            ->get();
        /** @var JournalDetail $oldDetail */
        foreach ($journalDetails as $oldDetail) {
            /** @var LedgerBalance $ledgerBalance */
            $ledgerBalance = $oldDetail->balances->first();
            $ledgerBalance->balance = bcsub(
                $ledgerBalance->balance,
                $oldDetail->amount,
                $this->ledgerCurrency->decimals
            );
            $ledgerBalance->save();
            $oldDetail->delete();
        }
    }

    /**
     * Get a journal entry by ID.
     *
     * @param int $id
     * @return JournalEntry
     * @throws Breaker
     */
    private function fetch(int $id): JournalEntry
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $journalEntry = JournalEntry::find($id);
        if ($journalEntry === null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('Journal entry :id does not exist', compact($id))]
            );
        }

        return $journalEntry;
    }

    /**
     * Fetch a Journal Entry.
     *
     * @param Entry $message
     * @return JournalEntry
     * @throws Breaker
     */
    public function get(Entry $message): JournalEntry
    {
        $message->validate(Message::OP_GET);
        return $this->fetch($message->id);
    }

    /**
     * Get currency details.
     *
     * @throws Breaker
     */
    private function getCurrency($currency)
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $this->ledgerCurrency = LedgerCurrency::find($currency);
        if ($this->ledgerCurrency === null) {
            throw Breaker::withCode(
                Breaker::INVALID_DATA,
                [__('Currency :code not found.', ['code' => $currency])]
            );
        }
    }

    /**
     * Process a query request.
     *
     * @param EntryQuery $message
     * @param int $opFlags
     * @return Collection Collection contains JournalEntry records.
     * @throws Breaker
     */
    public function query(EntryQuery $message, int $opFlags): Collection
    {
        $message->validate($opFlags);
        $query = $message->query();
        $query->orderBy('transDate')
            ->orderBy('journalEntryId');

        //$foo = $query->toSql();

        return $query->get();
    }

    /**
     * Perform a Journal Entry operation.
     *
     * @param Entry $message
     * @param int $opFlag
     * @return JournalEntry|null
     * @throws Breaker
     */
    public function run(Entry $message, int $opFlag): ?JournalEntry
    {
        // TODO: add POST operation!
        switch ($opFlag & Message::ALL_OPS) {
            case Message::OP_ADD:
                return $this->add($message);
            case Message::OP_DELETE:
                $this->delete($message);
                return null;
            case Message::OP_GET:
                return $this->get($message);
            case Message::OP_UPDATE:
                return $this->update($message);
            default:
                throw Breaker::withCode(Breaker::RULE_VIOLATION);
        }
    }

    /**
     * Update a Journal Entry.
     *
     * @param Entry $message
     * @return JournalEntry
     * @throws Breaker
     */
    public function update(Entry $message): JournalEntry
    {
        $this->validateEntry($message,Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $journalEntry = $this->fetch($message->id);
            $journalEntry->checkRevision($message->revision ?? null);

            DB::beginTransaction();
            $inTransaction = true;
            $journalEntry->fillFromMessage($message);
            $this->updateDetails($journalEntry, $message);
            $journalEntry->save();
            DB::commit();
            $inTransaction = false;
            $journalEntry->refresh();
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $journalEntry;
    }

    /**
     * Update entry details by undoing the existing details and creating the new ones.
     *
     * @param JournalEntry $journalEntry
     * @param Entry $message
     * @return void
     */
    protected function updateDetails(JournalEntry $journalEntry, Entry $message)
    {
        // Remove existing details, undoing balance changes
        $this->deleteDetails($journalEntry);
        $this->addDetails($journalEntry, $message);
    }

    /**
     * Perform an integrity check on the message.
     *
     * @param Entry $message
     * @param int $opFlag
     * @throws Breaker
     * @throws Exception
     */
    private function validateEntry(Entry $message, int $opFlag)
    {
        // First the basics
        $message->validate($opFlag);
        $errors = [];

        // Get the domain
        $message->domain ??= new EntityRef(LedgerAccount::rules()->domain->default);
        $this->ledgerDomain = LedgerDomain::findWith($message->domain)->first();
        if ($this->ledgerDomain === null) {
            $errors[] = __('Domain :domain not found.', ['domain' => $message->domain]);
        } else {
            $message->domain->uuid = $this->ledgerDomain->domainUuid;

            // Get the currency, use the domain default if none provided
            $message->currency ??= $this->ledgerDomain->currencyDefault;
            $this->getCurrency($message->currency);
        }

        // If a journal is supplied, verify the code
        if (isset($message->journal)) {
            $subJournal = SubJournal::findWith($message->journal)->first();
            if ($subJournal === null) {
                $errors[] = __('Journal :code not found.', ['code' => $message->journal->code]);
            }
        }

        // Normalize the amounts and check for balance
        $postToCategory = LedgerAccount::rules()->account->postToCategory;
        $balance = '0';
        $unique = [];
        $precision = $this->ledgerCurrency->decimals;
        foreach ($message->details as $line => $detail) {
            // Make sure the account is valid and that we have the uuid
            $ledgerAccount = $detail->findAccount();
            if ($ledgerAccount === null) {
                $errors[] = __('Detail line :line has an invalid account.', compact('line'));
            }
            if (!$postToCategory && $ledgerAccount->category) {
                $errors[] = __(
                    "Can't post to category account :code",
                    ['code' => $ledgerAccount->code]
                );
            }
            // Check that each account only appears once.
            if (isset($unique[$ledgerAccount->ledgerUuid])) {
                $errors[] = __(
                    'The account :code cannot appear more than once in an entry',
                    ['code' => $ledgerAccount->code]
                );
                continue;
            }
            $unique[$ledgerAccount->ledgerUuid] = true;

            // Make sure any reference is valid and that we have the uuid
            if (isset($detail->reference)) {
                $detail->reference->lookup();
            }
            $balance = bcadd($balance, $detail->normalizeAmount($precision));
        }
        if (bccomp($balance, '0') !== 0) {
            $errors[] = __('Entry amounts are out of balance by :balance.', compact('balance'));
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
        }
    }

}
