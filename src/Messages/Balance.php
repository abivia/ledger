<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;

class Balance extends Message
{
    public EntityRef $account;

    public string $amount;

    protected static array $copyable = [
        'currency', 'domain', 'language',
    ];

    public string $currency;

    public ?string $domain;

    public string $language;

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
    public static function fromRequest(array $data, int $opFlags): self
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
                $balance->account->code = $data['code'] ?? null;
            } else {
                $errors[] = __("Request requires account code.");
            }
            if (isset($data['amount'])) {
                $balance->amount = $data['amount'];
            }
        } elseif ($opFlags & self::OP_GET) {
            if (isset($data['uuid']) || isset($data['code'])) {
                $balance->account = new EntityRef();
                $balance->account->code = $data['code'] ?? null;
                $balance->account->uuid = $data['uuid'] ?? null;
            } else {
                $errors[] = __("Request requires either account code or uuid.");
            }
        }
        $balance->copy($data, $opFlags);

        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        if ($opFlags & self::F_VALIDATE) {
            $balance->validate($opFlags);
        }

        return $balance;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): self
    {
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
                || ($this->account->uuid === null && $this->account->code === null)
            ) {
                $errors[] = __("Request requires an account code or uuid.");
            }
        }
        // Clean up missing values with the defaults
        $this->domain ??= LedgerAccount::rules()->domain->default;
        $this->language ??= LedgerAccount::rules()->language->default;
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

}
