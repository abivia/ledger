<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;

use Abivia\Ledger\Http\Controllers\LedgerAccountController;
use Abivia\Ledger\Messages\Account;
use Abivia\Ledger\Messages\AccountQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerAccountApiController
{
    use ControllerResultHandler;

    /**
     * Perform an account operation.
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
            $opFlags = Message::toOpFlags(
                $operation, ['add' => Message::F_API, 'disallow' => Message::OP_CREATE]
            );
            $controller = new LedgerAccountController();
            if ($opFlags & Message::OP_QUERY) {
                $message = AccountQuery::fromRequest($request, $opFlags);
                $accounts = [];
                foreach ($controller->query($message, $opFlags) as $entry) {
                    $accounts[] = $entry->toResponse([]);
                }
                $response['accounts'] = $accounts;
            } else {
                $message = Account::fromRequest($request, $opFlags);
                $ledgerAccount = $controller->run($message, $opFlags);
                if ($opFlags & Message::OP_DELETE) {
                    $response['success'] = true;
                } else {
                    $response['account'] = $ledgerAccount->toResponse();
                }
            }
        } catch (Breaker $exception) {
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
