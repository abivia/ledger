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
            foreach ($message->details as $detail) {
                $journalDetail = new JournalDetail();
                $journalDetail->journalEntryId = $journalEntry->journalEntryId;
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
     * @param string $domainCode
     * @return JournalEntry
     * @throws Breaker
     */
    private function fetch(string $domainCode): JournalEntry
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerDomain = JournalEntry::where('code', $domainCode)->first();
        if ($ledgerDomain === null) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__('domain :code does not exist', ['code' => $domainCode])]
            );
        }

        return $ledgerDomain;
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
        return $this->fetch($message->code);
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
        $message->validate(Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $ledgerDomain = $this->fetch($message->code);
            $ledgerDomain->checkRevision($message->revision ?? null);

            $codeChange = $message->toCode !== null
                && $ledgerDomain->code !== $message->toCode;
            if ($codeChange) {
                $ledgerDomain->code = $message->toCode;
            }

            if (isset($message->extra)) {
                $ledgerDomain->extra = $message->extra;
            }

            DB::beginTransaction();
            $inTransaction = true;
            $this->updateDetails($ledgerDomain, $message);
            $ledgerDomain->save();
            // If we just changed the default domain, update settings in ledger root.
            $flex = LedgerAccount::root()->flex;
            if ($codeChange && $flex->rules->domain->default === $message->code) {
                $flex->rules->domain->default = $ledgerDomain->code;
                LedgerAccount::root()->flex = $flex;
                LedgerAccount::saveRoot();
            }
            DB::commit();
            $inTransaction = false;
            $ledgerDomain->refresh();
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerDomain;
    }

    protected function updateDetails(JournalEntry $ledgerDomain, Entry $message)
    {
        foreach ($message->names as $name) {
            /** @var LedgerName $ledgerName */
            $ledgerName = $ledgerDomain->names->firstWhere('language', $name->language);
            if ($ledgerName === null) {
                $ledgerName = new LedgerName();
                $ledgerName->ownerUuid = $ledgerDomain->domainUuid;
                $ledgerName->language = $name->language;
            }
            $ledgerName->name = $name->name;
            $ledgerName->save();
        }
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
        if ($message->journal !== '') {
            $this->subJournal = SubJournal::findWith($message->journal)->first();
            if ($this->subJournal === null) {
                $errors[] = __('Journal :code not found.', ['code' => $message->journal]);
            } else {
                $this->subJournal = null;
            }
        }

        // Normalize the amounts and check for balance
        $balance = '0';
        $precision = $this->ledgerCurrency->decimals;
        foreach ($message->details as $line => $detail) {
            // Make sure the account is valid and that we have the uuid
            $ledgerAccount = $detail->findAccount();
            if ($ledgerAccount === null) {
                $errors[] = __('Detail line :line has an invalid account.', compact('line'));
            }
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
            bcadd($balance, $detail->normalizeAmount($precision));
        }
        if (bccomp($balance, '0') !== 0) {
            $errors[] = __('Entry amounts are out of balance by :balance.', compact('balance'));
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::INVALID_OPERATION, $errors);
        }
    }

}
