<?php

namespace Abivia\Ledger\Traits;


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait Audited
{
    protected function auditLog(object $message)
    {
        /** @noinspection PhpPossiblePolymorphicInvocationInspection */
        Log::channel(config('ledger.log'))
            ->withContext(['user' => Auth::user()->id ?? null])
            ->info(self::class, ['message' => json_encode($message)]);
    }

}
