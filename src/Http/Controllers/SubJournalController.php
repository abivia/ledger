<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Name;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Messages\SubJournal;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\SubJournal as LedgerSubJournal;
use Abivia\Ledger\Traits\Audited;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Manage sub-journals
 */
class SubJournalController extends Controller
{
    use Audited;

    /**
     * Add a sub-journal to the ledger.
     *
     * @param SubJournal $message
     * @return LedgerSubJournal
     * @throws Breaker
     * @throws Exception
     */
    public function add(SubJournal $message): LedgerSubJournal
    {
        $inTransaction = false;
        $message->validate(Message::OP_ADD);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (LedgerSubJournal::where('code', $message->code)->first() !== null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__("SubJournal :code already exists.", ['code' => $message->code])]
            );
        }

        try {
            DB::beginTransaction();
            $inTransaction = true;
            $ledgerSubJournal = LedgerSubJournal::createFromMessage($message);
            // Create the name records
            foreach ($message->names as $name) {
                $name->ownerUuid = $ledgerSubJournal->subJournalUuid;
                LedgerName::createFromMessage($name);
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

        return $ledgerSubJournal;
    }

    /**
     * Delete a sub-journal. The sub-journal must be unused.
     *
     * @param SubJournal $message
     * @return null
     * @throws Breaker
     * @throws Exception
     */
    public function delete(SubJournal $message)
    {
        $message->validate(Message::OP_DELETE);
        $errors = [];
        $ledgerSubJournal = $this->fetch($message->code);
        $ledgerSubJournal->checkRevision($message->revision ?? null);
        // Ensure there are no journal entries that use this domain
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = JournalEntry::where('subJournalUuid', $ledgerSubJournal->subJournalUuid)
            ->count();
        if ($used !== 0) {
            $errors[] = __(
                "Can't delete: transactions use the :code journal.",
                ['code' => $message->code]
            );
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
        }
        $inTransaction = false;
        try {
            DB::beginTransaction();
            $inTransaction = true;
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            LedgerName::where('ownerUuid', $ledgerSubJournal->subJournalUuid)->delete();
            $ledgerSubJournal->delete();
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return null;
    }

    /**
     * Fetch a sub-journal by code.
     *
     * @param string $subJournalCode
     * @return LedgerSubJournal
     * @throws Breaker
     */
    private function fetch(string $subJournalCode): LedgerSubJournal
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerSubJournal = LedgerSubJournal::where('code', $subJournalCode)->first();
        if ($ledgerSubJournal === null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('Journal :code does not exist', ['code' => $subJournalCode])]
            );
        }

        return $ledgerSubJournal;
    }

    /**
     * Fetch a sub-journal.
     *
     * @param SubJournal $message
     * @return LedgerSubJournal
     * @throws Breaker
     */
    public function get(SubJournal $message): LedgerSubJournal
    {
        $message->validate(Message::OP_GET);
        return $this->fetch($message->code);
    }

    /**
     * Perform a domain operation.
     *
     * @param SubJournal $message
     * @param int $opFlag
     * @return LedgerSubJournal|null
     * @throws Breaker
     */
    public function run(SubJournal $message, int $opFlag): ?LedgerSubJournal
    {
        switch ($opFlag & Message::ALL_OPS) {
            case Message::OP_ADD:
                return $this->add($message);
            case Message::OP_DELETE:
                return $this->delete($message);
            case Message::OP_GET:
                return $this->get($message);
            case Message::OP_UPDATE:
                return $this->update($message);
            default:
                throw Breaker::withCode(Breaker::RULE_VIOLATION);
        }
    }

    /**
     * Update a sub-journal.
     *
     * @param SubJournal $message
     * @return LedgerSubJournal
     * @throws Breaker
     */
    public function update(SubJournal $message): LedgerSubJournal
    {
        $message->validate(Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $ledgerSubJournal = $this->fetch($message->code);
            $ledgerSubJournal->checkRevision($message->revision ?? null);

            $codeChange = isset($message->toCode)
                && $ledgerSubJournal->code !== $message->toCode;
            if ($codeChange) {
                $ledgerSubJournal->code = $message->toCode;
            }

            if (isset($message->extra)) {
                $ledgerSubJournal->extra = $message->extra;
            }

            DB::beginTransaction();
            $inTransaction = true;
            $this->updateNames($ledgerSubJournal, $message);
            $ledgerSubJournal->save();
            DB::commit();
            $inTransaction = false;
            $ledgerSubJournal->refresh();
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerSubJournal;
    }

    /**
     * Update the names associated with this sub-journal.
     *
     * @param LedgerSubJournal $ledgerSubJournal
     * @param SubJournal $message
     * @return void
     */
    protected function updateNames(LedgerSubJournal $ledgerSubJournal, SubJournal $message)
    {
        /** @var Name $name */
        foreach ($message->names as $name) {
            $name->applyTo($ledgerSubJournal);
        }
    }

}
