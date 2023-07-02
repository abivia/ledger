<?php
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerAccount\AddController;
use Abivia\Ledger\Http\Controllers\LedgerAccount\RootController;
use Abivia\Ledger\Logic\AccountLogic;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\AccountQuery;
use Abivia\Ledger\Messages\Create;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Traits\Audited;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class LedgerAccountController extends Controller
{
    use Audited;
    use ControllerResultHandler;

    /**
     * @var stdClass Rules from the ledger root.
     */
    protected stdClass $rules;

    /**
     * Adding an account to the ledger.
     *
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     */
    public function add(Account $message): LedgerAccount
    {
        $controller = new AddController();
        return $controller->add($message);
    }

    /**
     * Fire a bad request exception.
     *
     * @param array $messages
     * @throws Breaker
     */
    protected function badRequest(array $messages)
    {
        $this->errors[] = __(
            "Errors in request: " . implode(', ', $messages) . "."
        );
        throw Breaker::withCode(Breaker::BAD_REQUEST, $this->errors);
    }

    /**
     * Create a new ledger root.
     *
     * @param Create $message
     * @return LedgerAccount
     * @throws Breaker
     */
    public function create(Create $message): LedgerAccount
    {
        $controller = new RootController();
        return $controller->create($message);
    }

    /**
     * Delete a ledger account (and all sub-accounts). The accounts must be unused.
     *
     * @param Account $message
     * @return null
     * @throws Breaker
     * @throws Exception
     */
    public function delete(Account $message)
    {
        $message->validate(Message::OP_DELETE);
        $this->errors = [];
        $ledgerAccount = $this->fetchAccount($message);
        $ledgerAccount->checkRevision($message->revision ?? null);

        // Fails if there are sub-accounts with associated transactions
        $logic = new AccountLogic();
        if (!$logic->delete($ledgerAccount)) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__("Can't delete: account or sub-accounts have transactions.")]
            );
        }
        $this->auditLog($message);

        return null;
    }

    /**
     * Fetch an account and validate it matches all request elements.
     *
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     */
    protected function fetchAccount(Account $message): LedgerAccount
    {
        // Preference given to UUID over code
        if (isset($message->uuid)) {
            $ledgerAccount = LedgerAccount::find($message->uuid);
            if ($ledgerAccount === null) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT,
                    [__("Account with uuid :uuid not found.", ['uuid' => $message->uuid])]
                );
            }
            if (isset($message->code)) {
                if ($ledgerAccount->code !== $message->code) {
                    throw Breaker::withCode(
                        Breaker::BAD_ACCOUNT,
                        [__(
                            "Account with :uuid does not match requested code :code.",
                            ['code' => $message->code]
                        )]
                    );
                }
            }
        } else {
            $ledgerAccount = LedgerAccount::where('code', $message->code)->first();
            if ($ledgerAccount === null) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT,
                    [__("Account with code :code not found.", ['code' => $message->code])]
                );
            }
        }

        return $ledgerAccount;
    }

    /**
     * Get a single account
     *
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     */
    public function get(Account $message): LedgerAccount
    {
        $message->validate(Message::OP_GET);
        return $this->fetchAccount($message);
    }

    /**
     * Return accounts matching a Query.
     *
     * @param AccountQuery $message
     * @param int $opFlags
     * @return Collection
     * @throws Breaker
     */
    public function query(AccountQuery $message, int $opFlags): Collection
    {
        $message->validate($opFlags);
        if (count($message->names)) {
            // This is somewhat perverse, but not as perverse as what Eloquent does.
            $dbPrefix = DB::getTablePrefix();
            $accounts = $dbPrefix . (new LedgerAccount())->getTable();
            $names = $dbPrefix . (new LedgerName())->getTable();

            // Get a list of all the account codes matching our criteria
            $codeQuery = DB::table($accounts)
                ->join($names, "$accounts.ledgerUuid", '=', "$names.ownerUuid")
                ->select('code')
                ->orderBy('code')
                ->limit($message->limit);
            if (isset($message->after)) {
                try {
                    $codeQuery = LedgerAccount::whereEntity('>', $message->after, $codeQuery);
                } catch (Exception $exception) {
                    throw Breaker::withCode(Breaker::BAD_ACCOUNT, [$exception->getMessage()]);
                }
            }
            $codeQuery = $codeQuery->where(function ($query) use ($message) {
                $query = $message->selectNames($query);
                return $message->selectCodes($query);
            });
            $codeList = $codeQuery->get();
            $query = LedgerAccount::with('names')
                ->whereIn('code', $codeList->pluck('code'))
                ->orderBy('code');
        } else {
            $query = LedgerAccount::query()
                ->with('names')
                ->orderBy('code');
            $query = $message->selectCodes($query);
        }
        $query->limit($message->limit);
        if (isset($message->after)) {
            try {
                $query = LedgerAccount::whereEntity('>', $message->after, $query);
            } catch (Exception $exception) {
                throw Breaker::withCode(Breaker::BAD_ACCOUNT, [$exception->getMessage()]);
            }
        }

        return $query->get();
    }

    /**
     * Perform an account operation.
     *
     * @param Account $message Account details.
     * @param int|null $opFlags The requested operation. See the {@see Message} constants.
     * @return LedgerAccount|null The related account, if any.
     * @throws Breaker
     */
    public function run(Account $message, ?int $opFlags = null): ?LedgerAccount
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
     * Update an account.
     *
     * @param Account $message The update request details.
     * @return LedgerAccount The updated account with new values.
     * @throws Breaker
     */
    public function update(Account $message): LedgerAccount
    {
        $message->validate(Message::OP_UPDATE);
        $this->errors = [];
        $inTransaction = false;
        try {
            $ledgerAccount = $this->fetchAccount($message);
            $ledgerAccount->checkRevision($message->revision);

            DB::beginTransaction();
            $inTransaction = true;
            if (isset($message->toCode) && $message->toCode !== $ledgerAccount->code) {
                // Check for duplicate
                if (LedgerAccount::where('code', $message->toCode)->first() !== null) {
                    throw Breaker::withCode(
                        Breaker::RULE_VIOLATION,
                        [__(
                            "Account :code already exists, can't update.",
                            ['code' => $message->toCode]
                        )]
                    );
                }
                $ledgerAccount->code = $message->toCode;
            }

            // Update the parent
            $ledgerParent = $this->updateParent($ledgerAccount, $message);

            // Apply a tax code change
            if (isset($message->taxCode)) {
                $ledgerAccount->taxCode = $message->taxCode;
            }

            // Apply a category flag
            $isCategory = $message->category ?? false;
            if ($ledgerAccount->category !== $isCategory) {
                if ($isCategory) {
                    // Verify that the parent is a category or root
                    if (!$ledgerParent->category) {
                        throw Breaker::withCode(
                            Breaker::RULE_VIOLATION,
                            [__(
                                "Account can't be a category because parent is not a category."
                            )]
                        );
                    }
                } else {
                    // Verify that no children are categories
                    $subCats = LedgerAccount::where('parentUuid', $ledgerAccount->ledgerUuid)
                        ->where('category', true)
                        ->count();
                    if ($subCats !== 0) {
                        throw Breaker::withCode(
                            Breaker::RULE_VIOLATION,
                            [__(
                                "Can't make account a non-category because at least"
                                . " one sub-account is a category."
                            )]
                        );
                    }
                }
            }

            $this->updateFlags($ledgerAccount, $message);
            $this->updateNames($ledgerAccount, $message);

            if (isset($message->extra)) {
                $ledgerAccount->extra = $message->extra;
            }
            $ledgerAccount->save();
            $ledgerAccount->refresh();
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerAccount;
    }

    /**
     * Update an account's flags.
     *
     * @param LedgerAccount $ledgerAccount
     * @param Account $message
     * @throws Breaker
     */
    protected function updateFlags(LedgerAccount $ledgerAccount, Account $message)
    {
        // Set debit/credit, then check
        if (isset($message->credit) && $ledgerAccount->credit != $message->credit) {
            $ledgerAccount->credit = $message->credit;
            $ledgerAccount->debit = !$ledgerAccount->credit;
        }
        if (isset($message->debit) && $ledgerAccount->debit != $message->debit) {
            $ledgerAccount->debit = $message->debit;
            $ledgerAccount->credit = !$ledgerAccount->debit;
        }
        if ($ledgerAccount->debit === $ledgerAccount->credit) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__(
                    "Accounts must be either debit or credit accounts."
                )]
            );
        }
        if (!$ledgerAccount->category) {
            // Must ensure that no sub-accounts are category accounts
            $subAccounts = LedgerAccount::where('ledgerUuid', $ledgerAccount->ledgerUuid)
                ->get();
            /** @var LedgerAccount $subAccount */
            foreach ($subAccounts as $subAccount) {
                if ($subAccount->category) {
                    throw Breaker::withCode(
                        Breaker::RULE_VIOLATION,
                        [__(
                            "Can't make account a non-category because it has category sub-accounts."
                        )]
                    );
                }
            }
        }
        if ($message->closed ?? false) {
            // We need to check balances before closing the account
            // Is it possible to have sub-accounts still open?
            // Does closing a parent account just mean you can't post to it?
            // For lack of answers to these questions...
            // TODO: implement this.
            throw Breaker::withCode(
                Breaker::NOT_IMPLEMENTED,
                [__("Account closing not yet implemented.")]
            );
        }
    }

    /**
     * Update the names associated with an account.
     *
     * @param LedgerAccount $ledgerAccount
     * @param Account $message
     * @return void
     * @throws Breaker
     */
    protected function updateNames(LedgerAccount $ledgerAccount, Account $message)
    {
        foreach ($message->names as $name) {
            // Check for duplicate names in other Ledger accounts
            $dupCount = LedgerAccount::where('ledgerUuid', '!=', $ledgerAccount->ledgerUuid)
                ->whereHas('names', function (Builder $query) use ($name) {
                    $query->where('language', $name->language)
                        ->where('name', $name->name);
                })
                ->count();
            if ($dupCount !== 0) {
                throw Breaker::withCode(
                    Breaker::RULE_VIOLATION,
                    __(
                        "An account with the same name already exists for account"
                        . " :code in language :language",
                        ['code' => $ledgerAccount->code, 'language' => $name->language]
                    )
                );
            }
            $name->applyTo($ledgerAccount);
            // Ensure there is at least one name remaining
            if (
                LedgerName::where('ownerUuid', $ledgerAccount->ledgerUuid)
                    ->count() === 0
            ) {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST, 'Account must have at least one name.'
                );
            }
        }
    }

    /**
     * Update an account's parent.
     *
     * @param LedgerAccount $ledgerAccount
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     * @throws Exception
     */
    protected function updateParent(LedgerAccount $ledgerAccount, Account $message): LedgerAccount
    {
        if (isset($message->parent)) {
            // Get the new parent record
            /** @var LedgerAccount $ledgerParent */
            $ledgerParent = LedgerAccount::findWith($message->parent)->first();
            if ($ledgerParent === null) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT, [__("Specified parent not found.")]
                );
            }
            // Ensure the account graph is acyclic and parents reach the root.
            LedgerAccount::parentPath($message->parent, new EntityRef($message->code));
            $ledgerAccount->parentUuid = $ledgerParent->ledgerUuid;
        } else {
            // Get the existing parent
            $ledgerParent = LedgerAccount::find($ledgerAccount->parentUuid);
        }

        return $ledgerParent;
    }

}
