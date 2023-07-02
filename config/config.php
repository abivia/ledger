<?php


return [
    'api' => true,
    'api_debug' => env('LEDGER_API_DEBUG_ALLOWED', true),
    // 'chartPath' => 'your/custom/path',
    'log' => env('LEDGER_LOG_CHANNEL', env('LOG_CHANNEL')),
    'middleware' => ['api'],
    'prefix' => 'api/ledger',
    // To pass API-specific options to a report, use the reportApiOptions
    // setting. Example:
    // 'reportApiOptions' => [
    //     'trialBalance' => [
    //         'maxDepth' => 3,
    //     ],
    // ],
    //
    // Extended reports with the reports setting. Reports is an array indexed by
    // report name that references the reporting class (which should extend
    // `Abivia\Ledger\Reports\AbstractReport`). If there is a reports setting,
    // it must list all reports available to the JSON API. Any reports omitted
    // from this list (e.g. trialBalance) will be inaccessible to the JSON API.
    // 'reports' => [
    //     'trialBalance' => Abivia\Ledger\Reports\TrialBalanceReport::class,
    // ],
    'session_key_prefix' => env('LEDGER_SESSION_PREFIX', 'ledger.'),
];
