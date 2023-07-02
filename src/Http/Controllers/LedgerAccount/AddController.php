<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\LedgerAccount;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerName;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\EntityRef;
use Abivia\Ledger\Messages\Message;
use Exception;
use Illuminate\Support\Facades\DB;

/**
 * Container for the adding an account to the ledger.
 */
class AddController extends LedgerAccountController
{

    /**
     * Add an account to the ledger.
     *
     * @param Account $message Details of the add request.
     * @return LedgerAccount The new account.
     * @throws Breaker
     */
    public function add(Account $message): LedgerAccount
    {
        $message->validate(Message::OP_ADD);
        $inTransaction = false;
        $this->errors = [];
        try {
            $this->validateContext($message);
            // Check for duplicate account code
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            if (LedgerAccount::where('code', $message->code)->first() !== null) {
                throw Breaker::withCode(
                    Breaker::RULE_VIOLATION,
                    [__("Account :code already exists.", ['code' => $message->code])]);
            }

            DB::beginTransaction();
            $inTransaction = true;
            $ledgerAccount = LedgerAccount::createFromMessage($message);
            // Create the name records
            $this->updateNames($ledgerAccount, $message);
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
     * Check that the request satisfies all the business rules.
     *
     * @param Account $message
     * @return void
     * @throws Breaker
     * @throws Exception
     */
    private function validateContext(Account $message): void
    {
        // No parent implies the ledger root
        if (!isset($message->parent)) {
            $message->parent = new EntityRef();
            $ledgerParent = LedgerAccount::root();
            $parents = [$ledgerParent];
        } else {
            // Fetch the parent
            /** @var LedgerAccount $ledgerParent */
            $ledgerParent = LedgerAccount::findWith($message->parent)->first();
            if ($ledgerParent === null) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT, [__("Specified parent not found.")]
                );
            }
            $parents = LedgerAccount::parentPath($message->parent, new EntityRef($message->code));
        }
        $message->parent->uuid = $ledgerParent->ledgerUuid;

        // Validate and inherit flags
        if (($message->category ?? false) && !$ledgerParent->category) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__("Can't create a category under a parent that is not a category.")]
            );
        }
        if (!(($message->credit ?? false) || ($message->debit ?? false))) {
            $found = false;
            foreach ($parents as $ancestor) {
                if (($ancestor->credit || $ancestor->debit)) {
                    $message->credit = $ancestor->credit;
                    $message->debit = $ancestor->debit;
                    $found = true;
                    break;
                }
            }
            if (!$found && !($message->category ?? false)) {
                throw Breaker::withCode(
                    Breaker::RULE_VIOLATION,
                    [__("Unable to inherit debit/credit status from parents.")]
                );
            }
        }
    }

}
