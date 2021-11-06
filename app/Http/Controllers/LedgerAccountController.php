<?php
/** @noinspection PhpArrayShapeAttributeCanBeAddedInspection */
/** @noinspection PhpDynamicAsStaticMethodCallInspection */
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\LedgerBalance;
use App\Models\LedgerName;
use App\Models\Messages\Ledger\Account;
use App\Traits\Audited;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Support\Facades\DB;
use stdClass;

class LedgerAccountController extends Controller
{
    use Audited;
    use ControllerResultHandler;

    protected stdClass $rules;

    /**
     * @param array $messages
     * @throws Breaker
     */
    protected function badRequest(array $messages)
    {
        $this->errors[] = __(
            "Errors in request: " . implode(', ', $messages) . "."
        );
        throw Breaker::withCode(Breaker::BAD_REQUEST);
    }

    /**
     * Delete a ledger account (and all sub-accounts). The accounts must be unused.
     *
     * @param Account $message
     * @return array
     * @throws Breaker
     */
    public function delete(Account $message): array
    {
        $this->errors = [];
        $inTransaction = false;
        try {
            $ledgerAccount = $this->fetchAccount($message);
            // Ensure there are no sub-accounts with associated transactions
            $relatedAccounts = $this->getSubAccountList($ledgerAccount->ledgerUuid);
            $accountTable = (new LedgerAccount())->getTable();
            $balanceTable = (new LedgerBalance())->getTable();
            $subCats = DB::table($accountTable)
                ->join($balanceTable,
                    $accountTable . '.ledgerUuid', '=',
                    $balanceTable . '.ledgerUuid'
                )
                ->whereIn($accountTable . '.ledgerUuid', $relatedAccounts)
                ->count();
            if ($subCats !== 0) {
                throw Breaker::withCode(
                    Breaker::INVALID_OPERATION,
                    [__("Can't delete: account or sub-accounts have transactions.")]
                );
            }
            $nameTable = (new LedgerName())->getTable();
            DB::beginTransaction();
            $inTransaction = true;
            Db::table($balanceTable)->whereIn('ledgerUuid', $relatedAccounts)
                ->delete();
            Db::table($nameTable)->whereIn('ledgerUuid', $relatedAccounts)
                ->delete();
            Db::table($accountTable)->whereIn('ledgerUuid', $relatedAccounts)
                ->delete();
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $relatedAccounts;
    }

    /**
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
     * Fetch a single account
     *
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     */
    public function get(Account $message): LedgerAccount
    {
        return $this->fetchAccount($message);
    }

    protected function getSubAccountList(string $ledgerUuid): array
    {
        $idList = [$ledgerUuid];
        $subAccounts = LedgerAccount::where('parentUuid', $ledgerUuid)->get();
        /** @var LedgerAccount $account */
        foreach ($subAccounts as $account) {
            $idList = array_merge($idList, $this->getSubAccountList($account->ledgerUuid));
        }

        return $idList;
    }

    /**
     * Update an account.
     *
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     */
    public function update(Account $message): LedgerAccount
    {
        $this->errors = [];
        $inTransaction = false;
        try {
            $ledgerAccount = $this->fetchAccount($message);
            $ledgerAccount->checkRevision($message->revision);

            DB::beginTransaction();
            $inTransaction = true;
            if ($message->code !== null) {
                $ledgerAccount->code = $message->code;
            }

            // Update the parent
            $ledgerParent = $this->updateParent($ledgerAccount, $message);

            // Apply a category flag
            if ($ledgerAccount->category !== $message->category) {
                if ($message->category) {
                    // Verify that the parent is a category or root
                    if (!$ledgerParent->category) {
                        $this->errors[] = __(
                            "Account can't be a category because parent is not a category."
                        );
                        throw Breaker::withCode(Breaker::INVALID_OPERATION);
                    }
                } else {
                    // Verify that no children are categories
                    $subCats = LedgerAccount::where('parentUuid', $ledgerAccount->ledgerUuid)
                        ->where('category', true)
                        ->count();
                    if ($subCats !== 0) {
                        throw Breaker::withCode(
                            Breaker::INVALID_OPERATION,
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
            DB::commit();
            $inTransaction = false;
            $ledgerAccount->refresh();
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
     * @param LedgerAccount $ledgerAccount
     * @param Account $message
     * @throws Breaker
     */
    protected function updateFlags(LedgerAccount $ledgerAccount, Account $message)
    {
        // Set debit/credit, then check
        if ($message->credit !== null && $ledgerAccount->credit != $message->credit) {
            $ledgerAccount->credit = $message->credit;
            $ledgerAccount->debit = !$ledgerAccount->credit;
        }
        if ($message->debit !== null && $ledgerAccount->debit != $message->debit) {
            $ledgerAccount->debit = $message->debit;
            $ledgerAccount->credit = !$ledgerAccount->debit;
        }
        if ($ledgerAccount->credit && $ledgerAccount->debit) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__(
                    "Account can not have both debit and credit flags set."
                )]
            );
        }
        if ($ledgerAccount->credit === false && $ledgerAccount->debit === false) {
            // Must ensure that no sub-accounts are category accounts
            // TODO: implement this.
            throw Breaker::withCode(
                Breaker::NOT_IMPLEMENTED,
                [__(
                    "Debit and credit both cleared not yet implemented."
                )]
            );
        }
        if ($message->closed) {
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

    protected function updateNames(LedgerAccount $ledgerAccount, Account $message)
    {
        foreach ($message->names as $name) {
            /** @var LedgerName $ledgerName */
            /** @noinspection PhpUndefinedMethodInspection */
            $ledgerName = $ledgerAccount->names->firstWhere('language', $name->language);
            if ($ledgerName === null) {
                $ledgerName = new LedgerName();
                $ledgerName->ownerUuid = $ledgerAccount->ledgerUuid;
                $ledgerName->language = $name->language;
            }
            $ledgerName->name = $name->name;
            $ledgerName->save();
        }
    }

    /**
     * @param LedgerAccount $ledgerAccount
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     * @throws Exception
     */
    protected function updateParent(LedgerAccount $ledgerAccount, Account $message): LedgerAccount
    {
        if ($message->parent !== null) {
            // Get the new parent record
            /** @var LedgerAccount $ledgerParent */
            $ledgerParent = LedgerAccount::findWith((array)$message->parent)->first();
            if ($ledgerParent === null) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT, [__("Specified parent not found.")]
                );
            }
            // TODO: need to ensure the account graph is acyclic and parents reach the root.
            $ledgerAccount->parentUuid = $ledgerParent->ledgerUuid;
        } else {
            // Get the existing parent
            $ledgerParent = LedgerAccount::find($ledgerAccount->parentUuid);
        }

        return $ledgerParent;
    }

}
