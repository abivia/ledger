<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class EntityRef extends Message
{
    /**
     * @var string The entity code (unique in context, may be empty)
     */
    public string $code;

    protected static array $copyable = [
        ['code', self::OP_ADD | self::OP_DELETE | self::OP_UPDATE],
        ['uuid', self::OP_ADD | self::OP_DELETE | self::OP_UPDATE],
    ];

    /**
     * @var ?string The UUID of the entity (can be null)
     */
    public ?string $uuid;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlag): self
    {
        $entityRef = new static();
        $entityRef->copy($data, $opFlag);
        if ($opFlag & self::FN_VALIDATE) {
            $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
            $entityRef->validate($opFlag, $codeFormat);
        }

        return $entityRef;
    }

    /**
     * Make sure the reference is valid.
     *
     * @param int $opFlag Operation bitmask.
     * @param string $codeFormat Regular expression for validating code property,
     * blank if not used.
     * @return self
     * @throws Breaker
     */
    public function validate(int $opFlag, string $codeFormat = ''): self
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
