<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Models\LedgerAccount;
use Abivia\Ledger\Messages\Message;

class EntityRef extends Message
{
    /**
     * @var ?string The entity code (unique in context, may be empty)
     */
    public string $code;

    protected static array $copyable = [
        'code',
        ['uuid', self::ALL_OPS & ~self::OP_ADD],
    ];

    /**
     * @var string The UUID of the entity.
     */
    public string $uuid;

    /**
     * @param string|null $code
     * @param string|null $uuid
     */
    public function __construct(string $code = null, string $uuid = null)
    {
        if ($code !== null) {
            $this->code = $code;
        }
        if ($uuid !== null) {
            $this->uuid = $uuid;
        }
    }

    public function __toString(): string
    {
        $parts = [];
        if (isset($this->code)) {
            $parts[] = 'code:' . $this->code;
        }
        if (isset($this->uuid)) {
            $parts[] = 'UUID:' . $this->uuid;
        }
        return '{' . implode(' / ', $parts) . '}';
    }

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD): self
    {
        $entityRef = new static();
        $entityRef->copy($data, $opFlags);
        if ($opFlags & self::F_VALIDATE) {
            $codeFormat = LedgerAccount::rules($opFlags & self::OP_CREATE)
                ->account->codeFormat ?? '';
            $entityRef->validate($opFlags, $codeFormat);
        }

        return $entityRef;
    }

    public static function fromMixed($data, int $opFlags = 0): EntityRef
    {
        if (is_array($data)) {
            $entityRef = EntityRef::fromArray($data, $opFlags);
        } else {
            $entityRef = new EntityRef();
            $entityRef->code = $data;
        }

        return $entityRef;
    }

    public function sameAs(EntityRef $ref): bool
    {
        if (($this->code ?? null) !== ($ref->code ?? null)) {
            return false;
        }
        if (($this->uuid ?? null) !== ($ref->uuid ?? null)) {
            return false;
        }
        return true;
    }

    /**
     * Make sure the reference is valid.
     *
     * @param int $opFlags Operation bitmask.
     * @param string $codeFormat Regular expression for validating code property,
     * blank if not used.
     * @return self
     * @throws Breaker
     */
    public function validate(?int $opFlags = null, string $codeFormat = ''): self
    {
        $errors = [];
        if (!isset($this->code) && !isset($this->uuid)) {
            $errors[] = 'parent must include at least one of code or uuid';
        }
        if (isset($this->code) && $codeFormat !== '') {
            if (!preg_match($codeFormat, $this->code)) {
                $errors[] = "account code must match the form $codeFormat";
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }
}
