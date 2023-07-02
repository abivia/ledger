<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Exceptions\Breaker;
use Abivia\Ledger\Helpers\Version;
use Abivia\Ledger\Messages\Message;
use Abivia\Ledger\Traits\ControllerResultHandler;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

abstract class ApiController
{
    use ControllerResultHandler;

    protected array $errors = [];

    protected function commonInfo(array $response): array
    {
        if (
            env('LEDGER_API_DEBUG_ALLOWED', true)
            && session(
                (config('ledger.session_key_prefix') ?? 'ledger.') . 'api_debug', 0
            ) > 0
        ) {
            $response['version'] = Version::core();
            $response['apiVersion'] = Version::api();
        }
        $response['time'] = new Carbon();

        return $response;
    }

    /**
     * Convert an operation request into a bitmask.
     *
     * @param string $operation
     * @return int
     * @throws Breaker
     */
    public static function getOpFlags(string $operation): int {
        return Message::toOpFlags(
            $operation, ['add' => Message::F_API, 'disallow' => Message::OP_CREATE]
        );
    }

    /**
     * Perform an API operation.
     *
     * @param Request $request
     * @param string $operation
     * @return array
     */
    public function run(Request $request, string $operation = ''): array
    {
        $this->errors = [];
        $response = [];
        try {
            $response = $this->runCore($request, $operation);
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

        return $this->commonInfo($response);
    }

    /**
     * @param Request $request
     * @param string $operation
     * @return array
     * @throws Breaker
     */
    abstract protected function runCore(Request $request, string $operation): array;

}
