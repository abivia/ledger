<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LedgerLogging
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $logChannel = env('LEDGER_LOG_CHANNEL', 'stack');
        Log::channel($logChannel)->withContext(
            [
                'user' => $request->user,
            ]
        );
        return $next($request);
    }
}
