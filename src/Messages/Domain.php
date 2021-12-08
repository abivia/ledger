<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Messages\Message;

class Domain extends Message
{
    public string $code;
    protected static array $copyable = [
        'code',
        'extra',
        ['revision', self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
        'uuid',
    ];

    public string $currencyDefault;
    public string $extra;
    /**
     * @var Name[]
     */
    public array $names = [];
    public string $revision;
    public bool $subJournals;
    public string $toCode;

    /**
     * @inheritdoc
     */
    public static function fromRequest(array $data, int $opFlags): self
    {
        $domain = new static();
        $domain->copy($data, $opFlags);
        if (isset($data['names'])) {
            $nameList = $data['names'] ?? [];
            if (isset($data['name'])) {
                array_unshift($nameList, ['name' => $data['name']]);
            }
            $domain->names = Name::fromRequestList($nameList, $opFlags, 1);
        }
        $domain->subJournals = $data['subJournals'] ?? false;
        if (isset($data['currency'])) {
            $domain->currencyDefault = $data['currency'];
        }
        if ($opFlags & self::F_VALIDATE) {
            $domain->validate($opFlags);
        }

        return $domain;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags): self
    {
        $errors = [];
        if (isset($this->code)) {
            $this->code = strtoupper($this->code);
        } else {
            $errors[] = 'the code property is required';
        }
        if (isset($this->currencyDefault)) {
            if ($this->currencyDefault === '') {
                $errors[] = 'Currency code cannot be empty';
            } else {
                $this->currencyDefault = strtoupper($this->currencyDefault);
            }
        }
        if ($opFlags & self::OP_ADD && count($this->names) === 0) {
            $errors[] = 'A non-empty names property is required';
        }
        if ($opFlags & self::OP_UPDATE && !isset($this->revision)) {
            $errors[] = 'A revision code is required';
        }
        if (isset($this->toCode)) {
            $this->toCode = strtoupper($this->toCode);
        }
        try {
            foreach ($this->names as $name) {
                $name->validate($opFlags);
            }
        } catch (Breaker $exception) {
            Merge::arrays($errors, $exception->getErrors());
        }
        if (count($errors)) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }
        return $this;
    }
}
