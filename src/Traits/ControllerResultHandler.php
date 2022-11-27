<?php

namespace Abivia\Ledger\Traits;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Merge;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Generate common messages when exceptions occur and write to the log.
 */
trait ControllerResultHandler
{
    protected array $errors = [];

    /**
     * @param QueryException $exception
     */
    protected function dbException(QueryException $exception): void
    {
        $this->errors[] = __(
            "Unexpected database exception code :code.",
            ['code' => $exception->getCode()]
        );
        Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
            ->error($exception->getMessage(), ['errors' => $this->errors]);
    }

    protected function success(Request $request)
    {
        Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
            ->info(
                $request->getQueryString() . ' success',
                ['input' => $request->all()]
            );
    }

    protected function unexpectedException(Exception $exception): string
    {
        $this->errors[] = $exception->getMessage() . ' at '
            . $exception->getFile() . ':' . $exception->getLine();
        Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
            ->critical('Unexpected exception', ['errors' => $this->errors]);
        return __('An unexpected error has occurred. Check the audit log for details.');
    }

    protected function warning(Exception $exception)
    {
        if ($exception instanceof Breaker) {
            Merge::arrays($this->errors, $exception->getErrors(true));
        }
        Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
            ->warning($exception->getMessage(), ['errors' => $this->errors]);
    }

}
