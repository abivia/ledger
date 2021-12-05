<?php

namespace Abivia\Ledger\Traits;

use Illuminate\Support\Facades\Log;

trait Audited
{
    protected function auditLog(object $message)
    {
        $foo = config('ledger.log');
        Log::channel(config('ledger.log'))
            ->info(
                self::class,
                ['message' => json_encode($message)]
            );
    }

}
