<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Http\Controllers\LedgerBalanceController;
use Abivia\Ledger\Messages\Balance;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Models\LedgerCurrency;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerBalanceApiController
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
                $operation,
                [
                    'add' => Message::F_API,
                    'disallow' => (Message::OP_ADD | Message::OP_DELETE | Message::OP_UPDATE)
                ]
            );
            $message = Balance::fromRequest($request, $opFlags);
            $controller = new LedgerBalanceController();
            $ledgerBalance = $controller->run($message, $opFlags);
            if ($ledgerBalance === null) {
                // The request is good but the account has no transactions, return zero.
                $ledgerCurrency = LedgerCurrency::find($message->currency);
                if ($ledgerCurrency === null) {
                    throw Breaker::withCode(
                        Breaker::INVALID_DATA,
                        __('Currency :code not found.', ['code' => $message->currency])
                    );
                }
                $message->amount = bcadd('0', '0', $ledgerCurrency->decimals);
            } else {
                $message->amount = $ledgerBalance->balance;
            }
            $response['balance'] = $message;
        } catch (Breaker $exception) {
            $this->errors[] = $exception->getErrors();
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
