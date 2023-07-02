<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Messages\Batch;
use Abivia\Ledger\Messages\Message;
use Illuminate\Http\Request;

class BatchApiController extends ApiController
{
    /**
     * Perform a batch operation.
     *
     * @param Request $request
     * @param string $operation
     * @return array
     * @throws Breaker
     * @throws \Exception
     */
    protected function runCore(Request $request, string $operation): array
    {
        $message = Batch::fromRequest($request, Message::OP_BATCH);

        return $message->run();
    }

}
