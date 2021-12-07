<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\JournalReference;
use Abivia\Ledger\Messages\Ledger\Reference;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\Audited;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Manage links to external resources.
 */
class JournalReferenceController extends Controller
{
    use Audited;

    /**
     * Add a reference for use in journal details.
     *
     * @param Reference $message
     * @return JournalReference
     * @throws Breaker
     * @throws Exception
     */
    public function add(Reference $message): JournalReference
    {
        $inTransaction = false;
        $message->validate(Message::OP_ADD);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (JournalReference::where('code', $message->code)->first() !== null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [
                    __(
                        "Reference :code already exists.",
                        ['code' => $message->code]
                    )
                ]
            );
        }

        try {
            DB::beginTransaction();
            $inTransaction = true;
            $journalReference = JournalReference::createFromMessage($message);
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $journalReference;
    }

    /**
     * Delete a reference. The reference must be unused.
     *
     * @param Reference $message
     * @return null
     * @throws Breaker
     */
    public function delete(Reference $message)
    {
        $message->validate(Message::OP_DELETE);
        $journalReference = $this->fetch($message->code);
        // Ensure there are no journal entries that use this reference
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = JournalEntry::where(
            'journalReferenceUuid', $journalReference->journalReferenceUuid
            )
            ->count();
        if ($used !== 0) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__(
                    "Can't delete: transactions use the :code reference.",
                    ['code' => $message->code]
                )]
            );
        }
        // Single query, transactions not required.
        $journalReference->delete();
        $this->auditLog($message);

        return null;
    }

    /**
     * Retrieve a reference by code.
     *
     * @param string $referenceCode
     * @return JournalReference
     * @throws Breaker
     */
    private function fetch(string $referenceCode): JournalReference
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerReference = JournalReference::where('code', $referenceCode)->first();
        if ($ledgerReference === null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('domain :code does not exist', ['code' => $referenceCode])]
            );
        }

        return $ledgerReference;
    }

    /**
     * Fetch a reference.
     *
     * @param Reference $message
     * @return JournalReference
     * @throws Breaker
     */
    public function get(Reference $message): JournalReference
    {
        $message->validate(Message::OP_GET);
        return $this->fetch($message->code);
    }

    /**
     * Perform a reference operation.
     *
     * @param Reference $message
     * @param int $opFlag
     * @return JournalReference|null
     * @throws Breaker
     */
    public function run(Reference $message, int $opFlag): ?JournalReference
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
     * Update a reference.
     *
     * @param Reference $message
     * @return JournalReference
     * @throws Breaker
     */
    public function update(Reference $message): JournalReference
    {
        $message->validate(Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $journalReference = $this->fetch($message->code);

            $codeChange = $message->toCode !== null
                && $journalReference->code !== $message->toCode;
            if ($codeChange) {
                $journalReference->code = $message->toCode;
            }

            if (isset($message->extra)) {
                $journalReference->extra = $message->extra;
            }

            DB::beginTransaction();
            $inTransaction = true;
            $journalReference->save();
            DB::commit();
            $inTransaction = false;
            $journalReference->refresh();
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $journalReference;
    }

}
