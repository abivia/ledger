<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerAccount\CreateController;
use Abivia\Ledger\Messages\Ledger\Create;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
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
