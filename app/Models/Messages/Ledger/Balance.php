<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

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

    public function addAmountTo(string &$amount, int $decimals)
    {
        $amount = bcadd($amount, $this->amount, $decimals);
    }

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        if (!($opFlags & self::OP_GET | Message::OP_CREATE)) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
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
        if ($opFlags & self::FN_VALIDATE) {
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
                $this->account === null
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
