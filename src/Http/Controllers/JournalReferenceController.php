<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\Reference;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\JournalReference;
use Abivia\Ledger\Models\LedgerDomain;
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
        $this->checkDomain($message);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $journalReference = JournalReference::where('domainUuid', $message->domain->uuid)
            ->where('code', $message->code)
            ->first();
        if ($journalReference !== null) {
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
     * Verify that the message refers to a valid domain.
     * @param Reference $message
     * @return void
     * @throws Breaker
     */
    private function checkDomain(Reference $message)
    {
        if (!isset($message->domain->uuid)) {
            /** @var LedgerDomain $ledgerDomain */
            $ledgerDomain = LedgerDomain::findWith($message->domain)->first();
            if ($ledgerDomain === null) {
                throw Breaker::withCode(
                    Breaker::INVALID_DATA,
                    [
                        __('Unknown domain :code.', ['code' => $message->domain->code])
                    ]
                );
            }
            $message->domain->uuid = $ledgerDomain->domainUuid;
        }
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
        $journalReference = $this->fetch($message);
        $journalReference->checkRevision($message->revision ?? null);
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
     * @param Reference $message
     * @return JournalReference
     * @throws Breaker
     */
    private function fetch(Reference $message): JournalReference
    {
        $this->checkDomain($message);
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerReference = JournalReference::where('domainUuid', $message->domain->uuid)
            ->where('code', $message->code)
            ->first();
        if ($ledgerReference === null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [
                    __('domain :code does not exist in domain :domain',
                        ['code' => $message->code, 'domain' => $message->domain->code])
                ]
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
        return $this->fetch($message);
    }

    /**
     * Perform a reference operation.
     *
     * @param Reference $message
     * @param int|null $opFlags
     * @return JournalReference|null
     * @throws Breaker
     */
    public function run(Reference $message, ?int $opFlags = null): ?JournalReference
    {
        $opFlags ??= $message->getOpFlags();
        switch ($opFlags & Message::ALL_OPS) {
            case Message::OP_ADD:
                return $this->add($message);
            case Message::OP_DELETE:
                return $this->delete($message);
            case Message::OP_GET:
                return $this->get($message);
            case Message::OP_UPDATE:
                return $this->update($message);
            default:
                throw Breaker::withCode(Breaker::BAD_REQUEST, 'Unknown or invalid operation.');
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
            $journalReference = $this->fetch($message);
            $journalReference->checkRevision($message->revision ?? null);

            $codeChange = isset($message->toCode)
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
            $journalReference->refresh();
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

}
