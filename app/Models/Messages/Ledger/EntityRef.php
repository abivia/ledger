<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class EntityRef extends Message
{
    /**
     * @var ?string The entity code (unique in context, may be empty)
     */
    public ?string $code = null;

    protected static array $copyable = [
        ['code', self::OP_ADD | self::OP_DELETE | self::OP_UPDATE],
        ['uuid', self::OP_ADD | self::OP_DELETE | self::OP_UPDATE],
    ];

    /**
     * @var ?string The UUID of the entity (can be null)
     */
    public ?string $uuid = null;

    /**
     * @param string|null $code
     * @param string|null $uuid
     */
    public function __construct(string $code = null, string $uuid = null)
    {
        $this->code = $code;
        $this->uuid = $uuid;
    }

    public function __toString(): string
    {
        $parts = [];
        if ($this->code !== null) {
            $parts[] = 'code:' . $this->code;
        }
        if ($this->uuid !== null) {
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
        if ($opFlags & self::FN_VALIDATE) {
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
        if ($this->code === null && $this->uuid === null) {
            $errors[] = 'parent must include at least one of code or uuid';
        }
        if ($this->code !== null && $codeFormat !== '') {
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
