<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;

class Account extends Message
{
    /**
     * @var bool If set `true`, this will be a category account.
     */
    public bool $category;

    /**
     * @var bool If set `true`, the account will be closed.
     */
    public bool $closed;

    /**
     * @var string A unique identifier for the account.
     */
    public string $code;

    /**
     * @var array Copyable properties
     */
    protected static array $copyable = [
        'category', 'closed', 'code', 'credit',
        'debit',
        'extra',
        ['revision', self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
        'uuid',
    ];

    /**
     * @var bool If set `true` then this account will be reported in the credit column.
     */
    public bool $credit;

    /**
     * @var bool If set `true` then this account will be reported in the debit column.
     */
    public bool $debit;
    /**
     * @var string An arbitrary string for use by the application.
     */
    public string $extra;
    /**
     * @var Name[] A list of `Name` messages.
     */
    public array $names = [];

    /**
     * @var EntityRef An account reference that contains the code or UUID of the parent account.
     */
    public EntityRef $parent;

    /**
     * @var string The revision hash code for the account. Required on delete or update.
     */
    public string $revision;

    /**
     * @var string A new account code to be assigned in an update operation.
     */
    public string $toCode;

    /**
     * @var string The UUID for this account. Only valid on update/delete.
     */
    public string $uuid;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
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
                    $account->parent = EntityRef::fromArray($data['parent'], $opFlags);
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
    public function validate(int $opFlags = 0): self
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
            if (($this->credit ?? false) == ($this->debit ?? false)) {
                $errors[] = __(
                    "account :code must be either debit or credit",
                    ['code' => $this->code]
                );
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
            if (($this->credit ?? false) && ($this->debit ?? false)) {
                $errors[] = __(
                    "account :code can't be both debit and credit",
                    ['code' => $this->code]
                );
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
