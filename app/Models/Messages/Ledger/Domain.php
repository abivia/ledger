<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\Messages\Message;

class Domain extends Message
{
    public string $code;
    public string $currencyDefault;
    public array $names = [];
    public string $ownerUuid;
    public bool $subJournals;

    /**
     * @param array $data
     * @param int $opFlag
     * @return Domain
     * @throws Breaker
     */
    public static function fromRequest(array $data, int $opFlag): self
    {
        $domain = new static();
        if ($data['code'] ?? false) {
            $domain->code = $data['code'];
        }
        if (isset($data['names'])) {
            $domain->names = Name::fromRequestList($data['names'], $opFlag, 1);
        }
        $domain->subJournals = $data['subJournals'] ?? false;
        if (isset($data['currency'])) {
            $domain->currencyDefault = strtoupper($data['currency']);
        }
        if ($opFlag & self::OP_VALIDATE) {
            $domain->validate($opFlag);
        }

        return $domain;
    }

    /**
     * @param int $opFlag
     * @return Domain
     * @throws Breaker
     */
    public function validate(int $opFlag): self
    {
        $errors = [];
        if (!isset($this->code)) {
            $errors[] = 'the code property is required';
        }
        if (count($this->names) === 0) {
            $errors[] = 'A non-empty names property is required';
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
