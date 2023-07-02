<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;

use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\AccountQuery;
use Abivia\Ledger\Messages\Message;
use Illuminate\Http\Request;

class LedgerAccountApiController extends ApiController
{
    /**
     * Perform an account operation.
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
            $message = AccountQuery::fromRequest($request, $opFlags);
        } else {
            $message = Account::fromRequest($request, $opFlags);
        }

        return $message->run();
    }

}
