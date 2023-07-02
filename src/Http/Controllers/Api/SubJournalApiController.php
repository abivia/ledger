<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\SubJournal;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\SubJournalQuery;
use Illuminate\Http\Request;

class SubJournalApiController extends ApiController
{
    /**
     * Perform a sub-journal operation.
     *
     * @param Request $request
     * @param string $operation
     * @return array
     * @throws Breaker
     */
    protected function runCore(Request $request, string $operation): array
    {
        $response = [];
        $opFlags = self::getOpFlags($operation);
        if ($opFlags & Message::OP_QUERY) {
            $message = SubJournalQuery::fromRequest($request, $opFlags);
        } else {
            $message = SubJournal::fromRequest($request, $opFlags);
        }

        return $message->run();
    }

}
