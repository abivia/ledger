<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class Account extends Message
{
    public ?bool $category = null;
    public ?bool $closed = null;
    public ?string $code = null;
    public ?bool $credit = null;
    public ?bool $debit = null;
    /**
     * @var mixed
     */
    public $extra;
    /**
     * @var Name[]|null
     */
    public ?array $names = null;
    public ?ParentRef $parent = null;
    public ?string $revision = null;
    public ?string $uuid = null;

    /**
     * @param array $data
     * @param int $opFlag
     * @return Account
     * @throws Breaker
     */
    public static function fromRequest(array $data, int $opFlag): Account
    {
        $errors = [];
        $account = new static();
        if ($opFlag & self::OP_ADD) {
            if (isset($data['code'])) {
                $account->code = $data['code'];
            }
            if (!isset($data['code'])) {
                $errors[] = __("Request requires an account code.");
            }
            if (isset($data['uuid'])) {
                $errors[] = __("UUID not valid on account create.");
            }
        } else {
            if (isset($data['uuid']) || isset($data['code'])) {
                $account->code = $data['code'] ?? null;
                $account->uuid = $data['uuid'] ?? null;
            } else {
                $errors[] = __("Request requires either code or uuid.");
            }
        }
        if (isset($data['revision'])) {
            $account->revision = $data['revision'];
        }
        if ($opFlag & (self::OP_ADD | self::OP_UPDATE)) {
            try {
                $account->names = Name::fromRequestList(
                    $data['names'] ?? [],
                    $opFlag,
                    ($opFlag & self::OP_ADD) ? 1 : 0
                );
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
            if (isset($data['parent'])) {
                try {
                    $account->parent = ParentRef::fromRequest($data['parent'], $opFlag);
                } catch (Breaker $exception) {
                    Merge::arrays($errors, $exception->getErrors());
                }
            }
            $account->category = $data['category'] ?? null;
            $account->closed = $data['closed'] ?? null;
            $account->credit = $data['credit'] ?? null;
            $account->debit = $data['debit'] ?? null;
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        if ($opFlag & self::OP_VALIDATE) {
            $account->validate($opFlag);
        }

        return $account;
    }

    public function validate(int $opFlag): self
    {
        $errors = [];
        $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
        if ($opFlag & self::OP_ADD) {
            if ($this->code === null) {
                $errors[] = __("Request requires an account code.");
            } else {
                if ($codeFormat !== '') {
                    if (!preg_match($codeFormat, $this->code)) {
                        $errors[] = "account code must match the form $codeFormat";
                    }
                }
            }
            if ($this->uuid !== null) {
                $errors[] = __("UUID not valid on account create.");
            }
        } else {
            if ($this->uuid === null && $this->code === null) {
                $errors[] = __("Request requires either code or uuid.");
            }
        }
        if ($opFlag & self::OP_UPDATE && $this->revision === null) {
            $errors[] = __("Update request must supply a revision.");
        }
        if ($opFlag & (self::OP_ADD | self::OP_UPDATE)) {
            try {
                foreach ($this->names as $name) {
                    $name->validate($opFlag);
                }
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
            if ($this->parent !== null) {
                try {
                    $this->parent->validate($opFlag);
                } catch (Breaker $exception) {
                    Merge::arrays($errors, $exception->getErrors());
                }
            }
            if ($this->credit && $this->debit) {
                $errors[] = "account cannot be both debit and credit";
            }
        }
        if (
            $opFlag & self::OP_ADD
            && count($this->names ?? []) === 0
        ) {
            $errors[] = __("Account create must have at least one name element.");
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }

}
