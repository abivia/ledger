<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class Balance extends Message
{
    public EntityRef $account;

    public string $currency;

    public string $domain;

    public string $language;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlag): self
    {
        if (!$opFlag & self::OP_GET) {
            throw Breaker::withCode(
                Breaker::INVALID_OPERATION,
                [__('Only get requests are allowed.')]
            );
        }
        $errors = [];
        $balance = new static();

        if (isset($data['uuid']) || isset($data['code'])) {
            $balance->account = new EntityRef();
            $balance->account->code = $data['code'] ?? null;
            $balance->account->uuid = $data['uuid'] ?? null;
        } else {
            $errors[] = __("Request requires either code or uuid.");
        }
        if (isset($data['domain'])) {
            $balance->domain = $data['domain'];
        }
        if (isset($data['language'])) {
            $balance->domain = $data['language'];
        }

        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        if ($opFlag & self::FN_VALIDATE) {
            $balance->validate($opFlag);
        }

        return $balance;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlag): self
    {
        $errors = [];
        if ($opFlag & self::OP_GET) {
            if (
                $this->account === null
                || ($this->account->uuid === null && $this->account->code === null)
            ) {
                $errors[] = __("Request requires an account code or uuid.");
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

}
