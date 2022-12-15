<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerCurrencyController;
use Abivia\Ledger\Messages\Currency;
use Abivia\Ledger\Messages\CurrencyQuery;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerCurrencyApiController
{
    use ControllerResultHandler;

    /**
     * Perform a currency operation.
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
            $controller = new LedgerCurrencyController();
            if ($opFlags & Message::OP_QUERY) {
                $message = CurrencyQuery::fromRequest($request, $opFlags);
                $currencies = [];
                foreach ($controller->query($message, $opFlags) as $entry) {
                    $currencies[] = $entry->toResponse([]);
                }
                $response['currencies'] = $currencies;
            } else {
                $message = Currency::fromRequest($request, $opFlags);
                $ledgerCurrency = $controller->run($message, $opFlags);
                if ($opFlags & Message::OP_DELETE) {
                    $response['success'] = true;
                } else {
                    $response['currency'] = $ledgerCurrency->toResponse();
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
