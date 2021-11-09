<?php
declare(strict_types=1);

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
     * Adding a domain to the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function add(Request $request): array
    {
        $this->errors = [];
        $response = [];
        try {
            $message = Domain::fromRequest($request->all(), Message::OP_ADD);
            $controller = new LedgerDomainController();
            $ledgerDomain = $controller->add($message);
            $response['domain'] = $ledgerDomain->toResponse();
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
     * Delete a domain from the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request): array
    {
        $this->errors = [];
        $response = [];
        try {
            $message = Domain::fromRequest($request->all(), Message::OP_DELETE);
            $controller = new LedgerDomainController();
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
     * Fetch a domain from the ledger.
     *
     * @param Request $request
     * @return array
     */
    public function get(Request $request): array
    {
        $this->errors = [];
        $response = [];
        try {
            $message = Domain::fromRequest($request->all(), Message::OP_GET);
            $controller = new LedgerDomainController();
            $ledgerDomain = $controller->get($message);
            $response['domain'] = $ledgerDomain->toResponse();
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
     * Update a domain.
     *
     * @param Request $request
     * @return array
     */
    public function update(Request $request): array
    {
        $this->errors = [];
        $response = [];
        try {
            $message = Domain::fromRequest($request->all(), Message::OP_UPDATE);
            $controller = new LedgerDomainController();
            $ledgerDomain = $controller->update($message);
            $response['domain'] = $ledgerDomain->toResponse();
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
