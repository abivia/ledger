<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait Audited
{
    protected function auditLog(object $message)
    {
        Log::channel(env('LEDGER_LOG_CHANNEL', 'stack'))
            ->info(
                self::class,
                ['message' => json_encode($message)]
            );
    }

}
