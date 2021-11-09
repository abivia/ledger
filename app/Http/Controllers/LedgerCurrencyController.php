<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\Breaker;
use App\Models\JournalEntry;
use App\Models\LedgerBalance;
use App\Models\LedgerCurrency;
use App\Models\LedgerDomain;
use App\Models\Messages\Ledger\Currency;
use App\Models\Messages\Message;
use App\Traits\Audited;
use Exception;
use Illuminate\Support\Facades\DB;

class LedgerCurrencyController extends Controller
{
    use Audited;

    /**
     * Add a currency to the ledger.
     *
     * @param Currency $message
     * @return LedgerCurrency
     * @throws Breaker
     */
    public function add(Currency $message): LedgerCurrency
    {
        $message->validate(Message::OP_ADD);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (LedgerCurrency::where('code', $message->code)->first() !== null) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [
                    __(
                        "Currency :code already exists.",
                        ['code' => $message->code]
                    )
                ]
            );
        }

        $ledgerCurrency = new LedgerCurrency();
        $ledgerCurrency->code = $message->code;
        $ledgerCurrency->decimals = $message->decimals;
        $ledgerCurrency->save();
        $this->auditLog($message);
        $ledgerCurrency->refresh();

        return $ledgerCurrency;
    }

    /**
     * Delete a currency. The currency must be unused.
     *
     * @param Currency $message
     * @throws Breaker
     */
    public function delete(Currency $message)
    {
        $message->validate(Message::OP_DELETE);
        $errors = [];
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerCurrency = LedgerCurrency::find($message->code);
        if ($ledgerCurrency === null) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__(
                    'currency :code does not exist',
                    ['code' => $message->code]
                )]
            );
        }
        // Ensure there are no journal entries that use this currency
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = JournalEntry::where('currency', $message->code)->count();
        if ($used !== 0) {
            $errors[] = __(
                "Can't delete: transactions use the :code currency.",
                ['code' => $message->code]
            );
        }
        // Ensure there are no balances with this currency
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = LedgerBalance::where('currency', $message->code)->count();
        if ($used !== 0) {
            $errors[] = __(
                "Can't delete: ledger accounts use the :code currency.",
                ['code' => $message->code]
            );
        }
        // Ensure there are no domains using this currency as default
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $used = LedgerDomain::where('currencyDefault', $message->code)->count();
        if ($used !== 0) {
            $errors[] = __(
                "Can't delete: ledger domains use the :code currency.",
                ['code' => $message->code]
            );
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::INVALID_OPERATION, $errors);
        }
        $ledgerCurrency->delete();
        $this->auditLog($message);

        return null;
    }

    /**
     * @param string $currencyCode
     * @return LedgerCurrency
     * @throws Breaker
     */
    private function fetch(string $currencyCode): LedgerCurrency
    {
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        $ledgerCurrency = LedgerCurrency::find($currencyCode);
        if ($ledgerCurrency === null) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__('currency :code does not exist', ['code' => $currencyCode])]
            );
        }

        return $ledgerCurrency;
    }

    /**
     * Fetch a currency.
     *
     * @param Currency $message
     * @return LedgerCurrency
     * @throws Breaker
     */
    public function get(Currency $message): LedgerCurrency
    {
        $message->validate(Message::OP_GET);
        return $this->fetch($message->code);
    }

    /**
     * Perform a currency operation.
     *
     * @param Currency $message
     * @param int $opFlag
     * @return LedgerCurrency
     * @throws Breaker
     */
    public function run(Currency $message, int $opFlag): ?LedgerCurrency
    {
        switch ($opFlag) {
            case Message::OP_ADD:
                return $this->add($message);
            case Message::OP_DELETE:
                return $this->delete($message);
            case Message::OP_GET:
                return $this->get($message);
            case Message::OP_UPDATE:
                return $this->update($message);
            default:
                throw Breaker::withCode(Breaker::INVALID_OPERATION);
        }
    }

    /**
     * Update a currency.
     *
     * @param Currency $message
     * @return LedgerCurrency
     * @throws Breaker
     */
    public function update(Currency $message): LedgerCurrency
    {
        $message->validate(Message::OP_UPDATE);
        $inTransaction = false;
        try {
            $ledgerCurrency = $this->fetch($message->code);
            $ledgerCurrency->checkRevision($message->revision ?? null);

            if (isset($message->decimals)) {
                if ($message->decimals < $ledgerCurrency->decimals) {
                    /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                    $used = JournalEntry::where('currency', $ledgerCurrency->code)->count();
                    if ($used !== 0) {
                        throw Breaker::withCode(
                            Breaker::INVALID_OPERATION,
                            [__("Can't decrease the decimal size of a currency in use.")]
                        );
                    }
                }
                $ledgerCurrency->decimals = $message->decimals;
            }
            $codeChange = $message->toCode !== null
                && $ledgerCurrency->code !== $message->toCode;
            if ($codeChange) {
                $ledgerCurrency->code = $message->toCode;
            }

            DB::beginTransaction();
            $inTransaction = true;
            if ($codeChange) {
                // Update all transactions that use the currency
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                JournalEntry::where('currency', $message->code)
                    ->update(['currency' => $message->toCode]);
                // Update all balances
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                LedgerBalance::where('currency', $message->code)
                    ->update(['currency' => $message->toCode]);
                // Update all domains
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                LedgerDomain::where('currencyDefault', $message->code)
                    ->update(['currencyDefault' => $message->toCode]);
            }
            $ledgerCurrency->save();
            DB::commit();
            $inTransaction = false;
            $ledgerCurrency->refresh();
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerCurrency;
    }

}
