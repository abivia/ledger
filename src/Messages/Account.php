<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Models\LedgerAccount;

class Account extends Message
{
    use HasCodes, HasNames;

    /**
     * @var bool If set `true`, this will be a category account.
     */
    public bool $category;

    /**
     * @var bool If set `true`, the account will be closed.
     */
    public bool $closed;

    /**
     * @var array Copyable properties
     */
    protected static array $copyable = [
        'category', 'closed', 'code', 'credit',
        'debit',
        'extra',
        ['revision', self::OP_DELETE | self::OP_UPDATE],
        'taxCode',
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
     * @var EntityRef An account reference that contains the code or UUID of the parent account.
     */
    public EntityRef $parent;

    /**
     * @var string The revision hash code for the account. Required on delete or update.
     */
    public string $revision;

    /**
     * @var string An account code for tax purposes.
     */
    public string $taxCode;

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
            Merge::arrays($errors, $account->loadNames($data, $opFlags));
            if (isset($data['parent'])) {
                try {
                    $account->parent = EntityRef::fromMixed($data['parent'], $opFlags);
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

    public function inheritFlagsFrom(LedgerAccount $parent)
    {
        if (!isset($this->debit)) {
            $this->debit = $parent->debit;
        }
        if (!isset($this->credit)) {
            $this->credit = $parent->credit;
        }
    }

    public function run(): array
    {
        $controller = new LedgerAccountController();
        $ledgerAccount = $controller->run($this);
        if ($this->opFlags & Message::OP_DELETE) {
            $response = ['success' => true];
        } else {
            $response = ['account' => $ledgerAccount->toResponse()];
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        $codeFormat = LedgerAccount::rules($opFlags & self::OP_CREATE)
            ->account->codeFormat ?? '';
        $errors = $this->validateCodes($opFlags, ['regEx' => $codeFormat]);
        if ($opFlags & self::OP_ADD) {
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
            $this->requireRevision($errors);
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
