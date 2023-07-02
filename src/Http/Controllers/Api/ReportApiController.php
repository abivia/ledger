<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\ReportController;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Illuminate\Http\Request;

class ReportApiController extends ApiController
{
    use ControllerResultHandler;

    /**
     * Perform a domain operation.
     *
     * @param Request $request
     * @param string $operation Unused in reports
     * @return array
     * @throws Breaker
     */
    public function runCore(Request $request, string $operation = ''): array
    {
        $message = Report::fromRequest($request, Message::F_API | Message::OP_QUERY);
        return $message ->run();
    }

}
