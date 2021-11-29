<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerAccount\CreateController;
use App\Models\LedgerAccount;
use App\Models\Messages\Ledger\Create;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerCreateApiController
{
    use ControllerResultHandler;

    public function run(Request $request): array
    {
        $response = [];
        $this->errors = [];
        try {
            // The Ledger must be empty
            CreateController::checkNoLedgerExists();

            $message = Create::fromRequest($request->all(), Message::OP_ADD | Message::OP_CREATE);
            $controller = new CreateController();
            $ledgerAccount = $controller->create($message);
            //$response['whatever'] = $ledgerAccount->toResponse();

            // Add the ledger information to the response
            $response['ledger'] = $ledgerAccount->toResponse();
            //$this->success($message);
        } catch (Breaker $exception) {
            $this->warning($exception);
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }

        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }
        $response['time'] = new Carbon();


        return $response;
    }
}
