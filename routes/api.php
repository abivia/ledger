<?php

use Abivia\Ledger\Http\Controllers\Api\JournalEntryApiController;
use Abivia\Ledger\Http\Controllers\Api\JournalReferenceApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerAccountApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerCreateApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerCurrencyApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerDomainApiController;
use Abivia\Ledger\Http\Controllers\Api\SubJournalApiController;
use Abivia\Ledger\Http\Middleware\LedgerLogging;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware([LedgerLogging::class])->group(function () {
    Route::post('account/{operation}', [LedgerAccountApiController::class, 'run']);
    Route::post('currency/{operation}', [LedgerCurrencyApiController::class, 'run']);
    Route::post('domain/{operation}', [LedgerDomainApiController::class, 'run']);
    Route::post('entry/{operation}', [JournalEntryApiController::class, 'run']);
    Route::post('journal/{operation}', [SubJournalApiController::class, 'run']);
    Route::post('reference/{operation}', [JournalReferenceApiController::class, 'run']);
    Route::post('root/create', [LedgerCreateApiController::class, 'run']);
});
