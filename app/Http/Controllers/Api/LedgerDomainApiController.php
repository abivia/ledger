<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\Breaker;
use App\Http\Controllers\LedgerDomainController;
use App\Models\Messages\Ledger\Domain;
use App\Models\Messages\Message;
use App\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerDomainApiController
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
            $message = Domain::fromRequest($request->all(), Message::OP_ADD);
            $controller = new LedgerDomainController();
            $ledgerCurrency = $controller->add($message);
            $response['domain'] = $ledgerCurrency->toResponse();
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
            $message = Domain::fromRequest($request->all(), Message::OP_UPDATE);
            $controller = new LedgerDomainController();
            $ledgerCurrency = $controller->update($message);
            $response['domain'] = $ledgerCurrency->toResponse();
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
