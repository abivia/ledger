<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Domain;
use Abivia\Ledger\Messages\DomainQuery;
use Abivia\Ledger\Messages\Message;
use Illuminate\Http\Request;

class LedgerDomainApiController extends ApiController
{
    /**
     * Perform a domain operation.
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
            $message = DomainQuery::fromRequest($request, $opFlags);
        } else {
            $message = Domain::fromRequest($request, $opFlags);
        }

        return $message->run();
    }

}
