<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerCurrencyController;
use App\Models\Messages\Ledger\Currency;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerCurrencyApiController
{
    use ControllerResultHandler;

    /**
     * Adding a currency to the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function add(Request $request): array
    {
        $response = [];
        try {
            $message = Currency::fromRequest($request->all(), Message::OP_ADD);
            $controller = new LedgerCurrencyController();
            $ledgerCurrency = $controller->add($message);
            $response['currency'] = $ledgerCurrency->toResponse();
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
     * Delete a currency from the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request): array
    {
        $response = [];
        try {
            $message = Currency::fromRequest($request->all(), Message::OP_DELETE);
            $controller = new LedgerCurrencyController();
            $controller->delete($message);
            $response['success'] = true;
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
     * Fetch a currency from the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function get(Request $request): array
    {
        $response = [];
        try {
            $message = Currency::fromRequest($request->all(), Message::OP_GET);
            $controller = new LedgerCurrencyController();
            $ledgerCurrency = $controller->get($message);
            $response['currency'] = $ledgerCurrency->toResponse();
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
     * Update a currency.
     *
     * @param Request $request
     * @return array
     */
    public function update(Request $request): array
    {
        $response = [];
        try {
            $message = Currency::fromRequest($request->all(), Message::OP_UPDATE);
            $controller = new LedgerCurrencyController();
            $ledgerCurrency = $controller->update($message);
            $response['currency'] = $ledgerCurrency->toResponse();
        } catch (Breaker $exception) {
            $this->errors[] = $exception->getErrors();
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
