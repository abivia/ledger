<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Messages\CurrencyQuery;
use Abivia\Ledger\Messages\Message;
use Illuminate\Http\Request;

class LedgerCurrencyApiController extends ApiController
{
    /**
     * Perform a currency operation.
     *
     * @param Request $request
     * @param string $operation
     * @return array
     * @throws Breaker
     */
    protected function runCore(Request $request, string $operation): array
    {
        $opFlags = self::getOpFlags($operation);
        if ($opFlags & Message::OP_QUERY) {
            $message = CurrencyQuery::fromRequest($request, $opFlags);
        } else {
            $message = Currency::fromRequest($request, $opFlags);
        }

        return $message->run();
    }

}
