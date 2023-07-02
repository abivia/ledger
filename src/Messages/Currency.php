<?php

namespace Abivia\Ledger\Messages;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerCurrencyController;

class Currency extends Message
{
    use HasCodes;

    /**
     * @var array Copyable properties
     */
    protected static array $copyable = [
        'code',
        ['revision', self::OP_DELETE | self::OP_UPDATE],
        ['toCode', self::OP_UPDATE],
    ];

    /**
     * @var int The number of decimal places to use for this currency.
     */
    public int $decimals;

    /**
     * @var string The revision hash code for the account.
     */
    public string $revision;

    public function __construct(?string $code = null, ?int $decimals = null)
    {
        if ($code !== null) {
            $this->code = $code;
        }
        if ($decimals !== null) {
            $this->decimals = $decimals;
        }
    }

    /**
     * @inheritdoc
     */
    public static function fromArray(array $data, int $opFlags = self::OP_ADD) : self
    {
        $result = new static();
        $result->copy($data, $opFlags);
        if (
            !($opFlags & self::OP_DELETE)
            && isset($data['decimals'])
        ) {
            if (is_numeric($data['decimals'])) {
                $result->decimals = (int)$data['decimals'];
            } else {
                throw Breaker::withCode(
                    Breaker::BAD_REQUEST,
                    [__('value of decimals must be numeric')]
                );
            }
        }
        if ($opFlags & self::F_VALIDATE) {
            $result->validate($opFlags);
        }

        return $result;
    }

    public function run(): array
    {
        $controller = new LedgerCurrencyController();
        $ledgerCurrency = $controller->run($this);
        if ($this->opFlags & Message::OP_DELETE) {
            $response = ['success' => true];
        } else {
            $response = ['currency' => $ledgerCurrency->toResponse()];
        }

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function validate(?int $opFlags = null): self
    {
        $opFlags ??= $this->getOpFlags();
        $errors = $this->validateCodes($opFlags);
        if (!($opFlags & (self::OP_DELETE | self::OP_GET))) {
            if (!isset($this->decimals)) {
                $errors[] = __('a numeric decimals property is required');
            }
        }
        if ($opFlags & self::OP_UPDATE) {
            $this->requireRevision($errors);
        }
        if (count($errors) !== 0) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $this;
    }
}
