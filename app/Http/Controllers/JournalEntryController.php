<?php

namespace App\Http\Controllers;

use App\Exceptions\Breaker;
use App\Models\JournalDetail;
use App\Models\JournalEntry;
use App\Models\JournalReference;
use App\Models\LedgerBalance;
use App\Models\LedgerCurrency;
use App\Models\LedgerDomain;
use App\Models\LedgerName;
use App\Models\Messages\Ledger\Entry;
use App\Models\Messages\Message;
use App\Models\SubJournal;
use App\Traits\Audited;
use Exception;
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
     * @var SubJournal|null The journal for this entry (if other than the default).
     */
    private ?SubJournal $subJournal;

    /**
     * Add a domain to the ledger.
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
            $this->addDetails($journalEntry->journalEntryId, $message);
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

    private function addDetails(int $journalEntryId, Entry $message)
    {
        foreach ($message->details as $detail) {
            $journalDetail = new JournalDetail();
            $journalDetail->journalEntryId = $journalEntryId;
            $journalDetail->ledgerUuid = $detail->account->uuid;
            $journalDetail->amount = $detail->amount;
            if ($detail->reference !== null) {
                $journalDetail->journalReferenceUuid = $detail->reference->uuid;
            }
            $journalDetail->save();
            // Create/adjust the ledger balances
            $ledgerBalance = LedgerBalance::where(
                ['ledgerUuid', '=', $journalDetail->ledgerUuid],
                ['domainUuid', '=', $this->ledgerDomain->domainUuid],
                ['currency', '=', $this->ledgerCurrency->code],
            )->first();
            if ($ledgerBalance === null) {
                $ledgerBalance = new LedgerBalance();
                $ledgerBalance->ledgerUuid = $journalDetail->ledgerUuid;
                $ledgerBalance->domainUuid = $this->ledgerDomain->domainUuid;
                $ledgerBalance->currency = $this->ledgerCurrency->code;
                $ledgerBalance->balance = $journalDetail->amount;
            } else {
                $ledgerBalance->balance = bcadd(
                    $ledgerBalance->balance,
                    $journalDetail->amount,
                    $this->ledgerCurrency->decimals
                );
            }
            $ledgerBalance->save();
        }
    }

    /**
     * @param Entry $message
     * @throws Breaker
     */
    public function delete(Entry $message) {
        $inTransaction = false;
        // Ensure that the message contents are valid.
        $this->validateEntry($message, Message::OP_DELETE);

        try {
            DB::beginTransaction();
            $inTransaction = true;
            // Get the Journal entry
            $journalEntry = $this->fetch($message->id);
            $journalEntry->checkRevision($message->revision ?? null);
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

        return;
    }

    private function deleteDetails(JournalEntry $journalEntry)
    {
        $journalDetails = JournalDetail::with(
            ['balances' => function ($query) use ($journalEntry) {
                $query->where('currency', $journalEntry->currency);
            }])->where('journalEntryId', $journalEntry->journalEntryId)
            ->all();
        /** @var JournalDetail $oldDetail */
        foreach ($journalDetails as $oldDetail) {
            /** @var LedgerBalance $ledgerBalance */
            $ledgerBalance = $oldDetail->balances->first();
            $ledgerBalance->balance = bcsub($ledgerBalance->balance, $oldDetail->amount);
            $ledgerBalance->save();
        }
    }

    /**
     * @param string $domainCode
     * @return JournalEntry
     * @throws Breaker
     */
    private function fetch(int $id): JournalEntry
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $journalEntry = JournalEntry::find($id);
        if ($journalEntry === null) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__('Journal entry :id does not exist', compact($id))]
            );
        }

        return $journalEntry;
    }

    /**
     * Fetch a domain.
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
     * Perform a domain operation.
     *
     * @param Entry $message
     * @param int $opFlag
     * @return JournalEntry|null
     * @throws Breaker
     */
    public function run(Entry $message, int $opFlag): ?JournalEntry
    {
        switch ($opFlag) {
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
                throw Breaker::withCode(Breaker::INVALID_OPERATION);
        }
    }

    /**
     * Update a domain.
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

    protected function updateDetails(JournalEntry $journalEntry, Entry $message)
    {
        // Remove existing details, undoing balance changes
        $this->deleteDetails($journalEntry);
        $this->addDetails($journalEntry->journalEntryId, $message);
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
        $message->validate(Message::OP_ADD);
        $errors = [];

        // Get the domain
        $this->ledgerDomain = LedgerDomain::findWith($message->domain)->first();
        if ($this->ledgerDomain === null) {
            $errors[] = __('Domain :domain not found.', ['domain' => $message->domain]);
        } else {
            $message->domain->uuid = $this->ledgerDomain->domainUuid;

            // Get the currency, use the domain default if none provided
            $message->currency ??= $this->ledgerDomain->currencyDefault;
            $this->ledgerCurrency = LedgerCurrency::find($message->currency);
            if ($this->ledgerCurrency === null) {
                $errors[] = __('Currency :code not found.', ['code' => $message->currency]);
            }
        }

        // If a journal is supplied, verify the code
        if (isset($message->journal)) {
            $this->subJournal = SubJournal::findWith($message->journal)->first();
            if ($this->subJournal === null) {
                $errors[] = __('Journal :code not found.', ['code' => $message->journal->code]);
            } else {
                $this->subJournal = null;
            }
        }

        // Normalize the amounts and check for balance
        $balance = '0';
        $unique = [];
        $precision = $this->ledgerCurrency->decimals;
        foreach ($message->details as $line => $detail) {
            // Make sure the account is valid and that we have the uuid
            $ledgerAccount = $detail->findAccount();
            if ($ledgerAccount === null) {
                $errors[] = __('Detail line :line has an invalid account.', compact('line'));
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
            if ($detail->reference !== null) {
                /** @var JournalReference $reference */
                $reference = JournalReference::findWith($detail->reference)->first();
                if ($reference === null) {
                    $errors[] = __('Reference not found for detail line :line.', compact('line'));
                } else {
                    $detail->reference->uuid = $reference->journalReferenceUuid;
                }
            }
            $balance = bcadd($balance, $detail->normalizeAmount($precision));
        }
        if (bccomp($balance, '0') !== 0) {
            $errors[] = __('Entry amounts are out of balance by :balance.', compact('balance'));
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::INVALID_OPERATION, $errors);
        }
    }

}
