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
        'code', 'uuid',
    ];

    /**
     * @var ?string The UUID of the entity (can be null)
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
    public static function fromRequest(array $data, int $opFlags): self
    {
        $entityRef = new static();
        $entityRef->copy($data, $opFlags);
        if ($opFlags & self::F_VALIDATE) {
            $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
            $entityRef->validate($opFlags, $codeFormat);
        }

        return $entityRef;
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
    public function validate(int $opFlags, string $codeFormat = ''): self
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
