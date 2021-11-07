<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\Messages\Message;

class SubJournal extends Message
{
    public string $code;
    public array $names = [];

    public static function fromRequest(array $data, int $opFlag): self
    {
        $subJournal = new static();
        if (isset($data['code'])) {
            $subJournal->code = $data['code'];
        }
        if ($opFlag & (self::OP_ADD | self::OP_UPDATE)) {
            $subJournal->names = Name::fromRequestList(
                $data['names'] ?? [],
                $opFlag,
                ($opFlag & self::OP_ADD) ? 1 : 0
            );
        }
        if ($opFlag & self::OP_VALIDATE) {
            $subJournal->validate($opFlag);
        }

        return $subJournal;
    }


    public function validate(int $opFlag): self
    {
        $errors = [];
        if (!isset($this->code)) {
            $errors[] = __('the code property is required');
        }
        if ($opFlag & (self::OP_ADD | self::OP_UPDATE)
            && count($this->names) === 0
        ) {
            $errors[] = __('at least one name property is required');
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
