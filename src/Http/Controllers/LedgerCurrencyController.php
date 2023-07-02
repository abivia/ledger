<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\CurrencyQuery;
use Abivia\Ledger\Models\JournalEntry;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerBalance;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Models\LedgerDomain;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\Audited;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manage the currencies supported by the ledger.
 */
class LedgerCurrencyController extends Controller
{
    use Audited;

    /**
     * Add a currency to the ledger.
     *
     * @param Currency $message
     * @return LedgerCurrency
     * @throws Breaker
     * @throws Exception
     */
    public function add(Currency $message): LedgerCurrency
    {
        if (LedgerAccount::count() === 0) {
            LedgerAccount::notInitializedError();
        }
        $message->validate(Message::OP_ADD);
        // check for duplicates
        /** @noinspection PhpDynamicAsStaticMethodCallInspection */
        if (LedgerCurrency::where('code', $message->code)->first() !== null) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
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
        try {
            DB::beginTransaction();
            $inTransaction = true;
            $ledgerCurrency->save();
            $ledgerCurrency->refresh();
            DB::commit();
            $inTransaction = false;
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerCurrency;
    }

    /**
     * Delete a currency. The currency must be unused.
     *
     * @param Currency $message
     * @return null
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
                Breaker::RULE_VIOLATION,
                [__('currency :code does not exist', ['code' => $message->code])]
            );
        }
        $ledgerCurrency->checkRevision($message->revision ?? null);
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
            throw Breaker::withCode(Breaker::RULE_VIOLATION, $errors);
        }
        $ledgerCurrency->delete();
        $this->auditLog($message);

        return null;
    }

    /**
     * Fetch a currency based on currency code.
     *
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
                Breaker::RULE_VIOLATION,
                [__('currency :code does not exist', ['code' => $currencyCode])]
            );
        }

        return $ledgerCurrency;
    }

    /**
     * Get a currency.
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
     * Return currencies matching a Query.
     *
     * @param CurrencyQuery $message
     * @param int $opFlags
     * @return Collection
     * @throws Breaker
     */
    public function query(CurrencyQuery $message, int $opFlags): Collection
    {
        $message->validate($opFlags);
        $query = LedgerCurrency::query()
            ->orderBy('code');
        $query = $message->selectCodes($query);
        $query->limit($message->limit);
        if (isset($message->after)) {
            $query = $query->where('code', '>', $message->after);
        }

        return $query->get();
    }

    /**
     * Perform a currency operation.
     *
     * @param Currency $message
     * @param int|null $opFlags
     * @return LedgerCurrency|null
     * @throws Breaker
     */
    public function run(Currency $message, ?int $opFlags = null): ?LedgerCurrency
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
                            Breaker::RULE_VIOLATION,
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
                // Update all balances with the new code.
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                LedgerBalance::where('currency', $message->code)
                    ->update(['currency' => $message->toCode]);
                // Update all domains with the new code.
                /** @noinspection PhpDynamicAsStaticMethodCallInspection */
                LedgerDomain::where('currencyDefault', $message->code)
                    ->update(['currencyDefault' => $message->toCode]);
            }
            $ledgerCurrency->save();
            $ledgerCurrency->refresh();
            DB::commit();
            $this->auditLog($message);
        } catch (Exception $exception) {
            if ($inTransaction) {
                DB::rollBack();
            }
            throw $exception;
        }

        return $ledgerCurrency;
    }

}
