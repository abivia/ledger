<?php

namespace Abivia\Ledger\Http\Controllers\Api;

use Abivia\Ledger\Helpers\Version;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

abstract class ApiController
{
    protected array $errors = [];

    protected function commonInfo(array $response): array
    {
        if (
            env('LEDGER_API_DEBUG_ALLOWED', true)
            && session(config('ledger.session_key_prefix') . 'api_debug', 0) > 0
        ) {
            $response['version'] = Version::core();
            $response['apiVersion'] = Version::api();
        }
        $response['time'] = new Carbon();

        return $response;
    }

    abstract public function run(Request $request, string $operation): array;
}
