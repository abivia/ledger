<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Abivia\Ledger\Messages\Message;

class Domain extends Message
{
    /**
     * @var string A unique identifier for the Domain.
     */
    public string $code;

    protected static array $copyable = [
        'code',
        'extra',
        ['revision', self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
        'uuid',
    ];

    /**
     * @var string The Currency code that is used in journal entries by default.
     */
    public string $currencyDefault;

    /**
     * @var string An arbitrary string for use by the application.
     */
    public string $extra;

    /**
     * @var Name[] A list of names for the domain.
     */
    public array $names = [];

    /**
     * @var string The revision hash code for the Domain.
     */
    public string $revision;

    /**
     * @var bool Set true when the Domain has separate journals.
     */
    public bool $subJournals;

    /**
     * @var string A new Domain code to be assigned in an update operation.
     */
    public string $toCode;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = 0): self
    {
        $domain = new static();
        $domain->copy($data, $opFlags);
        if (isset($data['names']) || isset($data['name'])) {
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
    public function validate(int $opFlags = 0): self
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
