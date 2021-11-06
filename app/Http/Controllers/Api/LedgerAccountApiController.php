<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;

use App\Http\Controllers\LedgerAccount\AddController;
use App\Http\Controllers\LedgerAccountController;
use App\Models\Messages\Ledger\Account;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerAccountApiController
{
    use ControllerResultHandler;

    /**
     * Adding an account to the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function add(Request $request): array
    {
        $response = [];
        try {
            $message = Account::fromRequest($request->all(), Message::OP_ADD);
            $controller = new AddController();
            $ledgerAccount = $controller->run($message);
            $response['account'] = $ledgerAccount->toResponse();
        } catch (Breaker $exception) {
            $this->errors[] = $exception->getErrors();
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
            $response['errors'] = $this->errors;
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
            $this->errors[] = $exception->getMessage();
            $response['errors'] = $this->errors;
        }
        $response['time'] = new Carbon();

        return $response;
    }

    /**
     * Remove an account from the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request): array
    {
        $response = [];
        try {
            $message = Account::fromRequest($request->all(), Message::OP_DELETE);
            $controller = new LedgerAccountController();
            $relatedAccounts = $controller->delete($message);
            $response['accounts'] = $relatedAccounts;
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

    /**
     * Fetch an account from the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function get(Request $request): array
    {
        $response = [];
        try {
            $message = Account::fromRequest($request->all(), Message::OP_GET);
            $controller = new LedgerAccountController();
            $ledgerAccount = $controller->get($message);
            $response['account'] = $ledgerAccount->toResponse();
        } catch (Breaker $exception) {
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

    /**
     * Update a currency.
     *
     * @param Request $request
     * @return array
     */
    public function update(Request $request): array
    {
        $response = [];
        try {
            $message = Account::fromRequest($request->all(), Message::OP_UPDATE);
            $controller = new LedgerAccountController();
            $ledgerAccount = $controller->update($message);
            $response['account'] = $ledgerAccount->toResponse();
        } catch (Breaker $exception) {
            $this->warning($exception);
            $response['errors'] = $this->errors;
        } catch (QueryException $exception) {
            $this->dbException($exception);
        } catch (Exception $exception) {
            $this->unexpectedException($exception);
        }
        $response['time'] = new Carbon();

        return $response;
    }


}
