<?php
declare(strict_types=1);

namespace App\Http\Controllers\LedgerAccount;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerAccountController;
use App\Models\LedgerAccount;
use App\Models\LedgerName;
use App\Models\Messages\Ledger\Account;
use App\Models\Messages\Message;
use Exception;
use Illuminate\Support\Facades\DB;

class AddController extends LedgerAccountController
{

    /**
     * Adding an account to the ledger.
     *
     * @param Account $message
     * @return LedgerAccount
     * @throws Breaker
     */
    public function add(Account $message): LedgerAccount
    {
        $message->validate(Message::OP_ADD);
        $inTransaction = false;
        $this->errors = [];
        try {
            $this->validateContext($message);
            // check for duplicate
            /** @noinspection PhpDynamicAsStaticMethodCallInspection */
            if (LedgerAccount::where('code', $message->code)->first() !== null) {
                throw Breaker::withCode(
                    Breaker::INVALID_OPERATION,
                    [
                        __(
                            "Account :code already exists.",
                            ['code' => $message->code]
                        )
                    ]
                );
            }

            DB::beginTransaction();
            $inTransaction = true;
            $ledgerAccount = LedgerAccount::createFromMessage($message);
            // Create the name records
            foreach ($message->names as $name) {
                $name->ownerUuid = $ledgerAccount->ledgerUuid;
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

        return $ledgerAccount;
    }

    /**
     * @param Account $message
     * @return void
     * @throws Breaker
     * @throws Exception
     */
    private function validateContext(Account $message): void
    {
        // No parent implies the ledger root
        if (!isset($message->parent)) {
            $ledgerParent = LedgerAccount::root();
        } else {
            // Fetch the parent
            /** @var LedgerAccount $ledgerParent */
            $ledgerParent = LedgerAccount::findWith((array)$message->parent)->first();
            if ($ledgerParent === null) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT, [__("Specified parent not found.")]
                );
            }
            if ($ledgerParent->code === $message->code) {
                throw Breaker::withCode(
                    Breaker::BAD_ACCOUNT,
                    [__(
                        "Circular parent reference on account :code.",
                        ['code' => $message->code]
                    )]
                );
            }
        }
        $message->parent->uuid = $ledgerParent->ledgerUuid;

        // Validate and inherit flags
        if ($message->category && !$ledgerParent->category) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__("Can't create a category under a parent that is not a category.")]
            );
        }
        if (!($message->credit || $message->debit)) {
            if (!($ledgerParent->credit || $ledgerParent->debit)) {
                throw Breaker::withCode(
                    Breaker::INVALID_OPERATION,
                    [__("Unable to inherit debit/credit status from parent.")]
                );
            }
            $message->credit = $ledgerParent->credit;
            $message->debit = $ledgerParent->debit;
        }
    }

}
