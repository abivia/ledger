<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerAccount\RootController;
use Abivia\Ledger\Messages\Create;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class RootApiController extends ApiController
{
    use ControllerResultHandler;

    public function create(Request $request): array
    {
        $response = [];
        try {
            // The Ledger must be empty
            RootController::checkNoLedgerExists();
            $controller = new RootController();

            $message = Create::fromRequest($request, Message::OP_ADD | Message::OP_CREATE);
            $ledgerAccount = $controller->create($message);

            // Add the ledger information to the response
            $response['ledger'] = $ledgerAccount->toResponse();
            //$this->success($message);
        } catch (Breaker $exception) {
            $this->warning($exception);
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $response['errors'] = $this->errors;
            $response['errors'][] = $this->unexpectedException($exception);
        }

        if (count($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return $this->commonInfo($response);
    }

    protected function runCore(Request $request, string $operation): array
    {
        $response = [];
        if ($operation === 'create') {
            return $this->create($request);
        } elseif ($operation === 'templates') {
            return $this->templates();
        }

        $response['errors'] = [__(
            ':operation is not a valid operation',
            ['operation' => $operation]
        )];

        return $response;
    }

    public function templates(): array
    {
        $response = [];
        $this->errors = [];
        try {
            $response['templates'] = array_values(RootController::listTemplates());
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

        return $this->commonInfo($response);
    }

}
