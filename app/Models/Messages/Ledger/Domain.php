<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\Messages\Message;

class Domain extends Message
{
    public string $code;
    public string $currencyDefault;
    public array $names = [];
    public string $ownerUuid;
    public bool $subJournals;

    public static function fromRequest(array $data, int $opFlag): self
    {
        $errors = [];
        $status = true;
        $domain = new static();
        if ($data['code'] ?? false) {
            $domain->code = $data['code'];
        } else {
            $errors[] = 'the code property is required';
            $status = false;
        }
        if (!($data['names'] ?? false)) {
            $errors[] = 'the names property is required';
            $status = false;
        }
        if ($status) {
            try {
                $domain->names = Name::fromRequestList($data['names'], $opFlag, 1);
            } catch (Breaker $exception) {
                Merge::arrays($errors, $exception->getErrors());
            }
        }
        $domain->subJournals = $data['subJournals'] ?? false;
        if (isset($data['currency'])) {
            $domain->currencyDefault = strtoupper($data['currency']);
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $domain;
    }

}
