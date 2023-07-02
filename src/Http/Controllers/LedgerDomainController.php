<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Messages\DomainQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Traits\Audited;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manage domains within the ledger.
 */
class LedgerDomainController extends Controller
{
    use Audited;

    /**
     * Add a domain to the ledger.
     *
     * @param Domain $message
     * @return LedgerDomain
     * @throws Breaker
     * @throws Exception
     */
    public function add(Domain $message): LedgerDomain
    {
        $inTransaction = false;
        $message->validate(Message::OP_ADD);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (LedgerDomain::where('code', $message->code)->first() !== null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [
                    __(
                        "Domain :code already exists.",
                        ['code' => $message->code]
                    )
                ]
            );
        }

        try {
            DB::beginTransaction();
            $inTransaction = true;
            $ledgerDomain = LedgerDomain::createFromMessage($message);
            // Create the name records
            $this->updateNames($ledgerDomain, $message);
            DB::commit();
            //$ledgerDomain->refresh();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerDomain;
    }

    /**
     * Delete a domain. The domain must be unused.
     *
     * @param Domain $message
     * @return null
     * @throws Breaker
     * @throws Exception
     */
    public function delete(Domain $message)
    {
        $message->validate(Message::OP_DELETE);
        $errors = [];
        LedgerAccount::loadRoot();
        $defaultDomain = LedgerAccount::rules()->domain->default ?? null;
        if ($defaultDomain === $message->code) {
            $errors[] = __(
                "Can't delete: :code is the default domain.",
                ['code' => $message->code]
            );
        }
        $ledgerDomain = $this->fetch($message->code);
        $ledgerDomain->checkRevision($message->revision ?? null);
        // Ensure there are no journal entries that use this domain
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = JournalEntry::where('domainUuid', $message->code)->count();
        if ($used !== 0) {
            $errors[] = __(
                "Can't delete: transactions use the :code domain.",
                ['code' => $message->code]
            );
        }
        // Ensure there are no balances in this domain
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = LedgerBalance::where('domainUuid', $message->code)->count();
        if ($used !== 0) {
            $errors[] = __(
                "Can't delete: ledger accounts use the :code domain.",
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
            LedgerName::where('ownerUuid', $ledgerDomain->domainUuid)->delete();
            $ledgerDomain->delete();
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
     * @param string $domainCode
     * @return LedgerDomain
     * @throws Breaker
     */
    private function fetch(string $domainCode): LedgerDomain
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerDomain = LedgerDomain::where('code', $domainCode)->first();
        if ($ledgerDomain === null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('domain :code does not exist', ['code' => $domainCode])]
            );
        }

        return $ledgerDomain;
    }

    /**
     * Fetch a domain.
     *
     * @param Domain $message
     * @return LedgerDomain
     * @throws Breaker
     */
    public function get(Domain $message): LedgerDomain
    {
        $message->validate(Message::OP_GET);
        return $this->fetch($message->code);
    }

    /**
     * Return domains matching a Query.
     *
     * @param DomainQuery $message
     * @param int $opFlags
     * @return Collection
     * @throws Breaker
     */
    public function query(DomainQuery $message, int $opFlags): Collection
    {
        $message->validate($opFlags);
        if (count($message->names)) {
            // This is somewhat perverse, but not as perverse as what Eloquent does.
            $dbPrefix = DB::getTablePrefix();
            $journals = $dbPrefix . (new LedgerDomain())->getTable();
            $names = $dbPrefix . (new LedgerName())->getTable();
            // Get a list of all the Sub-Journal codes matching our criteria
            $codeQuery = DB::table($journals)
                ->join($names, "$journals.domainUuid", '=', "$names.ownerUuid")
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
            $query = LedgerDomain::with('names')
                ->whereIn('code', $codeList->pluck('code'))
                ->orderBy('code');
        } else {
            $query = LedgerDomain::with('names')
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
     * @param Domain $message
     * @param int|null $opFlags
     * @return LedgerDomain|null
     * @throws Breaker
     */
    public function run(Domain $message, ?int $opFlags = null): ?LedgerDomain
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
     * Update a domain.
     *
     * @param Domain $message
     * @return LedgerDomain
     * @throws Breaker
     */
    public function update(Domain $message): LedgerDomain
    {
        $message->validate(Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $ledgerDomain = $this->fetch($message->code);
            $ledgerDomain->checkRevision($message->revision);

            $codeChange = isset($message->toCode)
                && $ledgerDomain->code !== $message->toCode;
            if ($codeChange) {
                $ledgerDomain->code = $message->toCode;
            }

            if (isset($message->extra)) {
                $ledgerDomain->extra = $message->extra;
            }

            DB::beginTransaction();
            $inTransaction = true;
            $this->updateNames($ledgerDomain, $message);
            $ledgerDomain->save();
            // If we just changed the default domain, update settings in ledger root.
            $flex = LedgerAccount::root()->flex;
            if ($codeChange && $flex->rules->domain->default === $message->code) {
                $flex->rules->domain->default = $ledgerDomain->code;
                LedgerAccount::root()->flex = $flex;
                LedgerAccount::saveRoot();
            }
            $ledgerDomain->refresh();
            DB::commit();
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerDomain;
    }

    /**
     * Update the names associated with this domain.
     *
     * @param LedgerDomain $ledgerDomain
     * @param Domain $message
     * @return void
     * @throws Breaker
     */
    protected function updateNames(LedgerDomain $ledgerDomain, Domain $message): void
    {
        foreach ($message->names as $name) {
            $dupCount = LedgerDomain::where('domainUuid', '!=', $ledgerDomain->domainUuid)
                ->whereHas('names', function (Builder $query) use ($name) {
                    $query->where('language', $name->language)
                        ->where('name', $name->name);
                })
                ->count();
            if ($dupCount !== 0) {
                throw Breaker::withCode(
                    Breaker::RULE_VIOLATION,
                    __(
                        "A domain with the same name already exists for account"
                        . " :code in language :language",
                        ['code' => $ledgerDomain->code, 'language' => $name->language]
                    )
                );
            }
            $name->applyTo($ledgerDomain);
            // Ensure there is at least one name remaining
            if (
                LedgerName::where('ownerUuid', $ledgerDomain->domainUuid)
                    ->count() === 0
            ) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST, 'Domain must have at least one name.'
                );
            }
        }
    }

}
