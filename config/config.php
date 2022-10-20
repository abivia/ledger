<?php

use Abivia\Ledger\Reports\TrialBalanceReport;

return [
    'api' => true,
    // 'chartPath' => 'your/custom/path',
    'log' => env('LEDGER_LOG_CHANNEL', env('LOG_CHANNEL')),
    'middleware' => ['api'],
    'prefix' => 'api/ledger',
    'reports' => [
        'trialBalance' => TrialBalanceReport::class,
    ],
];
