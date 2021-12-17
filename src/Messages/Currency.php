<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Message;

class Currency extends Message
{
    /**
     * @var string A unique identifier for the currency.
     */
    public string $code;

    /**
     * @var int The number of decimal places to use for this currency.
     */
    public int $decimals;

    /**
     * @var string The revision hash code for the account.
     */
    public string $revision;

    /**
     * @var string A new currency code to be assigned in an update operation.
     */
    public string $toCode;

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = 0) : self
    {
        $errors = [];
        $result = new static();

        if (($data['code'] ?? false)) {
            $result->code = strtoupper($data['code']);
        }

        if (
            !($opFlags & self::OP_DELETE)
            && isset($data['decimals'])
            && is_numeric($data['decimals'])
        ) {
            $result->decimals = (int)$data['decimals'];
        }
        if ($opFlags & self::OP_UPDATE) {
            if (isset($data['revision'])) {
                $result->revision = $data['revision'];
            }
            if (isset($data['toCode'])) {
                $result->toCode = $data['toCode'];
            }
        }
        if ($opFlags & self::F_VALIDATE) {
            $result->validate($opFlags);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function validate(int $opFlags = 0): self
    {
        $errors = [];
        if (!isset($this->code)) {
            $errors[] = __('the code property is required');
        } else {
            $this->code = strtoupper($this->code);
        }
        if (isset($this->toCode)) {
            $this->toCode = strtoupper($this->toCode);
        }

        if (!($opFlags & (self::OP_DELETE | self::OP_GET))) {
            if (!isset($this->decimals)) {
                $errors[] = __('a numeric decimals property is required');
            }
        }
        if ($opFlags & self::OP_UPDATE) {
            if (!isset($this->revision)) {
                $errors[] = __('the revision property is required');
            }
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }
}
