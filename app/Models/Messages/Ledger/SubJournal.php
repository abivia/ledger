<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Models\Messages\Message;

class SubJournal extends Message
{
    public string $code;
    public array $names;

    public static function fromRequest(array $data, int $opFlag): self
    {
        $errors = [];
        $subJournal = new static();
        $status = true;
        if (!($data['code'] ?? false)) {
            $errors[] = 'the code property is required';
            $status = false;
        } else {
            $subJournal->code = $data['code'];
        }
        if (!($data['names'] ?? false)) {
            $errors[] = 'the names property is required';
            $status = false;
        }
        if ($status) {
            try {
                $subJournal->names = Name::fromRequestList($data['names'], $opFlag, 1);
            } catch (Breaker $exception) {
                $errors[] = $exception->getErrors();
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $subJournal;
    }


}
