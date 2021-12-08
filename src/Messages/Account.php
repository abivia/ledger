<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;

class Account extends Message
{
    public bool $category;
    public bool $closed;
    public string $code;
    protected static array $copyable = [
        'category', 'closed', 'code', 'credit',
        'debit',
        'extra',
        ['revision', self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
        'uuid',
    ];

    public bool $credit;
    public bool $debit;
    /**
     * @var mixed
     */
    public $extra;
    /**
     * @var Name[]|null
     */
    public array $names = [];
    public EntityRef $parent;
    public string $revision;
    public string $toCode;
    public string $uuid;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): Account
    {
        $errors = [];
        $account = new static();
        $account->copy($data, $opFlags);
        if ($opFlags & (self::OP_ADD | self::OP_UPDATE)) {
            try {
                $nameList = $data['names'] ?? [];
                if (isset($data['name'])) {
                    array_unshift($nameList, ['name' => $data['name']]);
                }
                $account->names = Name::fromRequestList(
                    $nameList, $opFlags, ($opFlags & self::OP_ADD) ? 1 : 0
                );
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
            if (isset($data['parent'])) {
                try {
                    $account->parent = EntityRef::fromRequest($data['parent'], $opFlags);
                } catch (Breaker $exception) {
                    Merge::arrays($errors, $exception->getErrors());
                }
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        if ($opFlags & self::F_VALIDATE) {
            $account->validate($opFlags);
        }

        return $account;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): self
    {
        $errors = [];
        $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
        if ($opFlags & self::OP_ADD) {
            if (!isset($this->code)) {
                $errors[] = __("Request requires an account code.");
            } else {
                if ($codeFormat !== '') {
                    if (!preg_match($codeFormat, $this->code)) {
                        $errors[] = "account code must match the form $codeFormat";
                    }
                }
            }
            if (isset($this->uuid)) {
                $errors[] = __("UUID not valid on account create.");
            }
        } else {
            if (!isset($this->uuid) && !isset($this->code)) {
                $errors[] = __("Request requires either code or uuid.");
            }
        }
        if ($opFlags & self::OP_UPDATE) {
            if (!isset($this->revision)) {
                $errors[] = __("Update request must supply a revision.");
            }
            if (isset($this->toCode)) {
                if ($codeFormat !== '') {
                    if (!preg_match($codeFormat, $this->toCode)) {
                        $errors[] = "toCode must match the form $codeFormat";
                    }
                }
            }
        }
        if ($opFlags & (self::OP_ADD | self::OP_UPDATE)) {
            try {
                foreach ($this->names as $name) {
                    $name->validate($opFlags);
                }
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
            if (isset($this->parent)) {
                try {
                    $this->parent->validate($opFlags, $codeFormat);
                } catch (Breaker $exception) {
                    Merge::arrays($errors, $exception->getErrors());
                }
            }
            if (($this->credit ?? false) && ($this->debit ?? false)) {
                $errors[] = "account cannot be both debit and credit";
            }
        }
        if (
            $opFlags & self::OP_ADD
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
