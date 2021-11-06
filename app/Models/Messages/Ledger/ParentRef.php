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
        $errors = [];
        $parentRef = new static();
        $empty = true;
        if (isset($data['code'])) {
            $parentRef->code = $data['code'];
            $empty = false;
        }
        if (isset($data['uuid'])) {
            $parentRef->uuid = $data['uuid'];
            $empty = false;
        }
        if ($empty) {
            $errors[] = 'parent must include at least one of code or uuid';
        }
        $codeFormat = LedgerAccount::rules()->account->codeFormat ?? '';
        if (isset($result['code']) && $codeFormat !== '') {
            if (!preg_match($codeFormat, $result['code'])) {
                $errors[] = "account code must match the form $codeFormat";
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $parentRef;
    }

}
