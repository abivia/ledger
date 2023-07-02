<?php

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\JournalDetail;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
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
    private function addDetails(JournalEntry $journalEntry, Entry $message): void
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
    public function delete(Entry $message)
    {
        $inTransaction = false;
        // Ensure that the message contents are valid.
        $message->validate(Message::OP_DELETE);

        try {
            DB::beginTransaction();
            $inTransaction = true;
            // Get the Journal entry
            $journalEntry = $this->fetch($message->id);
            $journalEntry->checkRevision($message->revision ?? null);
            $journalEntry->checkUnlocked();

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
    private function deleteDetails(JournalEntry $journalEntry): void
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
                [__('Journal entry :id does not exist', ['id' => $id])]
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
     * Place a lock on a journal entry.
     *
     * @param Entry $message
     * @return JournalEntry
     * @throws Breaker
     */
    public function lock(Entry $message): JournalEntry
    {
        $message->validate(Message::OP_LOCK);
        $inTransaction = false;
        try {
            DB::beginTransaction();
            $inTransaction = true;

            $journalEntry = $this->fetch($message->id);
            $journalEntry->checkRevision($message->revision ?? null);
            $journalEntry->locked = $message->lock;
            $journalEntry->save();
            $journalEntry->refresh();
            DB::commit();
            $this->auditLog($message);
            $inTransaction = false;
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $journalEntry;
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
     * @param int|null $opFlags
     * @return JournalEntry|null
     * @throws Breaker
     */
    public function run(Entry $message, ?int $opFlags = null): ?JournalEntry
    {
        // TODO: add POST operation.
        $opFlags ??= $message->getOpFlags();
        switch ($opFlags & Message::ALL_OPS) {
            case Message::OP_ADD:
                return $this->add($message);
            case Message::OP_DELETE:
                $this->delete($message);
                return null;
            case Message::OP_GET:
                return $this->get($message);
            case Message::OP_LOCK:
                return $this->lock($message);
            case Message::OP_UPDATE:
                return $this->update($message);
            default:
                throw Breaker::withCode(Breaker::BAD_REQUEST, 'Unknown or invalid operation.');
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
        $this->validateEntry($message, Message::OP_UPDATE);
        $inTransaction = false;
        try {
            DB::beginTransaction();
            $inTransaction = true;

            $journalEntry = $this->fetch($message->id);
            $journalEntry->checkRevision($message->revision ?? null);
            $journalEntry->checkUnlocked();

            $journalEntry->fillFromMessage($message);
            $this->updateDetails($journalEntry, $message);
            $journalEntry->save();
            $journalEntry->refresh();
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
     * Update entry details by undoing the existing details and creating the new ones.
     *
     * @param JournalEntry $journalEntry
     * @param Entry $message
     * @return void
     */
    protected function updateDetails(JournalEntry $journalEntry, Entry $message): void
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
            $message->journal->uuid = $subJournal->subJournalUuid;
        }

        if ($this->ledgerDomain === null && count($errors) !== 0) {
            // Without the currency there is no point in going further.
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
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
                $errors[] = __(
                    'Detail line :line has an invalid account :account/:uuid.',
                    [
                        'line' => $line,
                        'account' => $detail->account->code ?? 'null',
                        'uuid' => $detail->account->uuid ?? 'null'
                    ]
                );
                continue;
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
                if (!isset($detail->reference->domain)) {
                    $detail->reference->domain = $message->domain;
                } elseif (!$detail->reference->domain->sameAs($message->domain)) {
                    $errors[] = __(
                        'Reference in Detail line :line has a mismatched domain.',
                        compact('line')
                    );
                }
                $detail->reference->lookup();
            }
            $balance = bcadd($balance, $detail->normalizeAmount($precision), $precision);
        }
        if (bccomp($balance, '0') !== 0) {
            $errors[] = __('Entry amounts are out of balance by :balance.', compact('balance'));
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
        }
    }

}
