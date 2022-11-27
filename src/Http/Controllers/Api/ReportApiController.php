<?php
declare(strict_types=1);

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\ReportController;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Messages\Report;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportApiController
{
    use ControllerResultHandler;

    /**
     * Perform a domain operation.
     *
     * @param Request $request
     * @return array
     */
    public function run(Request $request): array
    {
        $this->errors = [];
        $response = [];
        try {
            $message = Report::fromRequest($request, Message::F_API | Message::OP_QUERY);
            $controller = new ReportController();
            $report = $controller->generate($message);

            // Remove keys from the accounts so that they get sent as an array.
            $report['accounts'] = $report['accounts']->values();
            $response['report'] = $report;
        } catch (Breaker $exception) {
            $this->errors[] = $exception->getErrors();
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $response['errors'] = $this->errors;
            $response['errors'][] = $this->unexpectedException($exception);
        }
        $response['time'] = new Carbon();

        return $response;
    }

}
