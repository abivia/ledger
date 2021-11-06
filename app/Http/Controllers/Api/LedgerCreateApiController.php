<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;
use App\Helpers\Revision;
use App\Http\Controllers\LedgerAccount\InitializeController;
use App\Models\LedgerAccount;
use App\Models\Messages\Ledger\Create;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LedgerCreateApiController
{
    use ControllerResultHandler;

    public function run(Request $request): array
    {
        $response = [];
        $this->errors = [];
        try {
            // The Ledger must be empty
            InitializeController::checkNoLedgerExists();

            $message = Create::fromRequest($request->all(), Message::OP_ADD);
            // Set up the ledger boot rules object before loading anything.
            if ($request->has('rules')) {
                // Recode and decode the rules as objects
                LedgerAccount::bootRules($request->input('rules'));
            }

            $controller = new InitializeController();
            $ledgerAccount = $controller->run($message);
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
