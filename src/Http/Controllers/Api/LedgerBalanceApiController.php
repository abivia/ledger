<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Balance;
use Abivia\Ledger\Messages\Message;
use Illuminate\Http\Request;

class LedgerBalanceApiController extends ApiController
{
    /**
     * Convert the operation request into a bitmask.
     *
     * @param string $operation
     * @return int
     * @throws Breaker
     */
    public static function getOpFlags(string $operation): int {
        return Message::toOpFlags(
            $operation,
            [
                'add' => Message::F_API,
                'disallow' => (Message::OP_ADD | Message::OP_DELETE | Message::OP_UPDATE)
            ]
        );
    }

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
        $message = Balance::fromRequest($request, $opFlags);

        return $message->run();
    }

}
