<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Entry;
use Abivia\Ledger\Messages\EntryQuery;
use Abivia\Ledger\Messages\Message;
use Illuminate\Http\Request;

class JournalEntryApiController extends ApiController
{
    /**
     * Perform a journal entry operation.
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
            $message = EntryQuery::fromRequest($request, $opFlags);
        } else {
            $message = Entry::fromRequest($request, $opFlags);
        }

        return $message->run();
    }

}
