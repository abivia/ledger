<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\LedgerAccount;
use App\Models\Messages\Message;

class ParentRef extends Message
{
    /**
     * @var string
     */
    public string $code;
    /**
     * @var string
     */
    public string $uuid;

    /**
     * @throws Breaker
     */
    public static function fromRequest(array $data, int $opFlag): self
    {
        $parentRef = new static();
        if (isset($data['code'])) {
            $parentRef->code = $data['code'];
        }
        if (isset($data['uuid'])) {
            $parentRef->uuid = $data['uuid'];
        }
        if ($opFlag & self::OP_VALIDATE) {
            $parentRef->validate($opFlag);
        }

        return $parentRef;
    }

    /**
     * @throws Breaker
     */
    public function validate(int $opFlag): self
    {
        $errors = [];
        if (!isset($this->code) && !isset($this->uuid)) {
            $errors[] = 'parent must include at least one of code or uuid';
        }
        $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
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
