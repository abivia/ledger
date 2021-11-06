<?php

namespace App\Models\Messages\Ledger;


use App\Exceptions\Breaker;
use App\Models\Messages\Message;
use Illuminate\Http\Request;

class Currency extends Message
{
    public string $code;
    public int $decimals;
    public ?string $revision;
    public ?string $toCode = null;

    /**
     * Validate request data to define a currency.
     *
     * @param array $data
     * @param string $method The request method.
     * @return Currency
     * @throws Breaker
     */
    public static function fromRequest(array $data, int $opFlag)
    : Currency {
        $errors = [];
        $result = new static();
        $status = true;

        if (!($data['code'] ?? false)) {
            $errors[] = __('the code property is required');
            $status = false;
        } else {
            $result->code = strtoupper($data['code']);
        }

        if (!($opFlag & self::OP_DELETE)) {
            $hasDecimals = isset($data['decimals']);
            $decimalsIsNumeric = $hasDecimals && is_numeric($data['decimals']);
            if ($decimalsIsNumeric) {
                $result->decimals = (int)$data['decimals'];
            } else {
                if ($opFlag & self::OP_ADD) {
                    $errors[] = __('a numeric decimals property is required');
                    $status = false;
                } elseif ($hasDecimals) {
                    $errors[] = __('decimals property must be numeric');
                    $status = false;
                }
            }
        }
        if ($opFlag & self::OP_UPDATE) {
            if (!($data['revision'] ?? false)) {
                $errors[] = __('the revision property is required');
                $status = false;
            } else {
                $result->revision = $data['revision'];
            }
            if ($data['toCode'] ?? false) {
                $result->toCode = strtoupper($data['toCode']);
            }
        }
        if (!$status) {
            throw Breaker::withCode(Breaker::BAD_REQUEST, $errors);
        }

        return $result;
    }
}
