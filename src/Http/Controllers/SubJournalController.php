<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\Name;
use Abivia\Ledger\Messages\SubJournal;
use Abivia\Ledger\Messages\SubJournalQuery;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Models\SubJournal as SubJournalModel;
use Abivia\Ledger\Traits\Audited;
use Exception;
use Illuminate\Database\Eloquent\Collection;
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
     * @return SubJournalModel
     * @throws Breaker
     * @throws Exception
     */
    public function add(SubJournal $message): SubJournalModel
    {
        $inTransaction = false;
        $message->validate(Message::OP_ADD);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (SubJournalModel::where('code', $message->code)->first() !== null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__("SubJournal :code already exists.", ['code' => $message->code])]
            );
        }

        try {
            DB::beginTransaction();
            $inTransaction = true;
            $SubJournalModel = SubJournalModel::createFromMessage($message);
            // Create the name records
            foreach ($message->names as $name) {
                $name->ownerUuid = $SubJournalModel->subJournalUuid;
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

        return $SubJournalModel;
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
        $SubJournalModel = $this->fetch($message->code);
        $SubJournalModel->checkRevision($message->revision ?? null);
        // Ensure there are no journal entries that use this domain
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = JournalEntry::where('subJournalUuid', $SubJournalModel->subJournalUuid)
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
            LedgerName::where('ownerUuid', $SubJournalModel->subJournalUuid)->delete();
            $SubJournalModel->delete();
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
     * @return SubJournalModel
     * @throws Breaker
     */
    private function fetch(string $subJournalCode): SubJournalModel
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $SubJournalModel = SubJournalModel::where('code', $subJournalCode)->first();
        if ($SubJournalModel === null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('Journal :code does not exist', ['code' => $subJournalCode])]
            );
        }

        return $SubJournalModel;
    }

    /**
     * Fetch a sub-journal.
     *
     * @param SubJournal $message
     * @return SubJournalModel
     * @throws Breaker
     */
    public function get(SubJournal $message): SubJournalModel
    {
        $message->validate(Message::OP_GET);
        return $this->fetch($message->code);
    }

    /**
     * Return domains matching a Query.
     *
     * @param SubJournalQuery $message
     * @param int $opFlags
     * @return Collection
     * @throws Breaker
     */
    public function query(SubJournalQuery $message, int $opFlags): Collection
    {
        $message->validate($opFlags);
        if (count($message->names)) {
            // This is somewhat perverse, but not as perverse as what Eloquent does.
            $dbPrefix = DB::getTablePrefix();
            $journals = $dbPrefix . (new SubJournalModel())->getTable();
            $names = $dbPrefix . (new LedgerName())->getTable();
            // Get a list of all the Sub-Journal codes matching our criteria
            $codeQuery = DB::table($journals)
                ->join($names, "$journals.subJournalUuid", '=', "$names.ownerUuid")
                ->select('code')
                ->orderBy('code')
                ->limit($message->limit);
            if (isset($message->after)) {
                $codeQuery = $codeQuery->where('code', '>', $message->after);
            }
            $codeQuery = $codeQuery->where(function ($query) use ($message) {
                $query = $message->selectNames($query);
                return $message->selectCodes($query);
            });
            $foo = $codeQuery->toSql();
            $codeList = $codeQuery->get();
            $query = SubJournalModel::with('names')
                ->whereIn('code', $codeList->pluck('code'))
                ->orderBy('code');
        } else {
            $query = SubJournalModel::with('names')
                ->orderBy('code');
            $query = $message->selectCodes($query);
        }
        $query->limit($message->limit);
        if (isset($message->after)) {
            $query = $query->where('code', '>', $message->after);
        }

        return $query->get();
    }

    /**
     * Perform a domain operation.
     *
     * @param SubJournal $message
     * @param int|null $opFlags
     * @return SubJournalModel|null
     * @throws Breaker
     */
    public function run(SubJournal $message, ?int $opFlags = null): ?SubJournalModel
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
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST, 'Unknown or invalid operation.'
                );
        }
    }

    /**
     * Update a sub-journal.
     *
     * @param SubJournal $message
     * @return SubJournalModel
     * @throws Breaker
     */
    public function update(SubJournal $message): SubJournalModel
    {
        $message->validate(Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $SubJournalModel = $this->fetch($message->code);
            $SubJournalModel->checkRevision($message->revision ?? null);

            $codeChange = isset($message->toCode)
                && $SubJournalModel->code !== $message->toCode;
            if ($codeChange) {
                $SubJournalModel->code = $message->toCode;
            }

            if (isset($message->extra)) {
                $SubJournalModel->extra = $message->extra;
            }

            DB::beginTransaction();
            $inTransaction = true;
            $this->updateNames($SubJournalModel, $message);
            $SubJournalModel->save();
            $SubJournalModel->refresh();
            DB::commit();
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $SubJournalModel;
    }

    /**
     * Update the names associated with this sub-journal.
     *
     * @param SubJournalModel $SubJournalModel
     * @param SubJournal $message
     * @return void
     * @throws Breaker
     */
    protected function updateNames(SubJournalModel $SubJournalModel, SubJournal $message): void
    {
        foreach ($message->names as $name) {
            $name->applyTo($SubJournalModel);
        }
        // Ensure there is at least one name remaining
        if (
            LedgerName::where('ownerUuid', $SubJournalModel->subJournalUuid)
            ->count() === 0
        ) {
            throw Breaker::withCode(
                Breaker::BAD_REQUEST,
                'Journal must have at least one name.'
            );
        }
    }

}
