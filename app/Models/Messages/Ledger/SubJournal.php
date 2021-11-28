<?php

namespace App\Models\Messages\Ledger;

use App\Exceptions\Breaker;
use App\Helpers\Merge;
use App\Models\Messages\Message;

class SubJournal extends Message
{
    public string $code;
    /**
     * @var mixed
     */
    public $extra;
    public array $names = [];
    public string $revision;
    public string $toCode;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        $subJournal = new static();
        if (isset($data['code'])) {
            $subJournal->code = $data['code'];
        }
        if ($opFlags & (self::OP_ADD | self::OP_UPDATE)) {
            $nameList = $data['names'] ?? [];
            if (isset($data['name'])) {
                array_unshift($nameList, ['name' => $data['name']]);
            }
            $subJournal->names = Name::fromRequestList(
                $nameList, $opFlags, ($opFlags & self::OP_ADD) ? 1 : 0
            );
        }
        if (isset($data['extra'])) {
            $subJournal->extra = $data['extra'];
        }
        if ($opFlags & self::OP_UPDATE) {
            if (isset($data['revision'])) {
                $subJournal->revision = $data['revision'];
            }
            if (isset($data['toCode'])) {
                $subJournal->toCode = strtoupper($data['toCode']);
            }
        }
        if ($opFlags & self::F_VALIDATE) {
            $subJournal->validate($opFlags);
        }

        return $subJournal;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): self
    {
        $errors = [];
        if (!isset($this->code)) {
            $errors[] = __('the code property is required');
        }
        if ($opFlags & self::OP_ADD && count($this->names) === 0) {
            $errors[] = __('at least one name property is required');
        }
        if ($opFlags & self::OP_UPDATE && !isset($this->revision)) {
            $errors[] = 'A revision code is required';
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
