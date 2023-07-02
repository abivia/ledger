<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerBalanceController;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Models\LedgerCurrency;

class Balance extends Message
{
    /**
     * @var EntityRef The unique code for the referenced account.
     */
    public EntityRef $account;

    /**
     * @var string Return value: the current account balance.
     */
    public string $amount;

    protected static array $copyable = [
        'currency', 'domain',
    ];

    /**
     * @var string The currency of the requested balance.
     */
    public string $currency;

    /**
     * @var EntityRef Ledger domain. If not provided the default is used.
     */
    public EntityRef $domain;

    /**
     * Add this balance amount to the passed amount.
     *
     * @param string $amount Passed by reference.
     * @param int $decimals Number of decimals to keep.
     * @return string The updated amount.
     */
    public function addAmountTo(string &$amount, int $decimals): string
    {
        $amount = bcadd($amount, $this->amount, $decimals);

        return $amount;
    }

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_CREATE): self
    {
        if (!($opFlags & self::OP_GET | Message::OP_CREATE)) {
            throw Breaker::withCode(
                Breaker::RULE_VIOLATION,
                [__('Only create and get requests are allowed.')]
            );
        }
        $errors = [];
        $balance = new static();

        if ($opFlags & self::OP_CREATE) {
            if (isset($data['code'])) {
                $balance->account = new EntityRef();
                $balance->account->code = $data['code'];
            } else {
                $errors[] = __("Request requires account code.");
            }
            if (isset($data['amount'])) {
                $balance->amount = $data['amount'];
            }
        } elseif ($opFlags & self::OP_GET) {
            if (isset($data['uuid']) || isset($data['code'])) {
                $balance->account = new EntityRef();
                if (isset($data['code'])) {
                    $balance->account->code = $data['code'];
                }
                if (isset($data['uuid'])) {
                    $balance->account->uuid = $data['uuid'];
                }
            } else {
                $errors[] = __("Request requires either account code or uuid.");
            }
        }
        $balance->copy($data, $opFlags);
        if (isset($data['domain'])) {
            $balance->domain = EntityRef::fromMixed($data['domain']);
        }

        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        if ($opFlags & self::F_VALIDATE) {
            $balance->validate($opFlags);
        }

        return $balance;
    }

    public function run(): array
    {
        $controller = new LedgerBalanceController();
        $ledgerBalance = $controller->run($this, $this->opFlags);
        if ($ledgerBalance === null) {
            // The request is good but the account has no transactions, return zero.
            $ledgerCurrency = LedgerCurrency::find($this->currency);
            if ($ledgerCurrency === null) {
                throw Breaker::withCode(
                    Breaker::INVALID_DATA,
                    __('Currency :code not found.', ['code' => $this->currency])
                );
            }
            $this->amount = bcadd('0', '0', $ledgerCurrency->decimals);
        } else {
            $this->amount = $ledgerBalance->balance;
        }
        $response = ['balance' => $this];

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        $errors = [];
        if ($opFlags & self::OP_CREATE) {
            if (($this->account ?? null) === null || $this->account->code === null) {
                $errors[] = __("Request requires an account code.");
            }
            if (!isset($this->amount)) {
                $errors[] = __("Opening balance must have an amount.");
            }
        } elseif ($opFlags & self::OP_GET) {
            if (
                !isset($this->account)
                || (!isset($this->account->uuid) && !isset($this->account->code))
            ) {
                $errors[] = __("Request requires an account code or uuid.");
            }
        }
        // Clean up missing values with the defaults
        $this->domain ??= new EntityRef(LedgerAccount::rules()->domain->default);
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

}
