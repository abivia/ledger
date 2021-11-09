<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;
use App\Http\Controllers\SubJournalController;
use App\Models\Messages\Ledger\SubJournal;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SubJournalApiController
{
    use ControllerResultHandler;

    /**
     * Perform a sub-journal operation.
     *
     * @param Request $request
     * @param string $operation
     * @return array
     */
    public function run(Request $request, string $operation): array
    {
        $this->errors = [];
        $response = [];
        try {
            $opFlag = Message::toOpFlag($operation, Message::OP_CREATE);
            if ($opFlag === 0) {
                throw Breaker::withCode(
                    Breaker::INVALID_OPERATION,
                    [':operation is not a valid function.', ['operation' => $operation]]
                );
            }
            $message = SubJournal::fromRequest($request->all(), $opFlag);
            $controller = new SubJournalController();
            $subJournal = $controller->run($message, $opFlag);
            if ($opFlag & Message::OP_DELETE) {
                $response['success'] = true;
            } else {
                $response['journal'] = $subJournal->toResponse();
            }
        } catch (Breaker $exception) {
            $this->errors[] = $exception->getErrors();
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }
        $response['time'] = new Carbon();

        return $response;
    }

}
