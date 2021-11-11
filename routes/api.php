<?php

use App\Http\Controllers\Api\JournalReferenceApiController;
use App\Http\Controllers\Api\LedgerAccountApiController;
use App\Http\Controllers\Api\LedgerCreateApiController;
use App\Http\Controllers\Api\LedgerCurrencyApiController;
use App\Http\Controllers\Api\LedgerDomainApiController;
use App\Http\Controllers\Api\SubJournalApiController;
use App\Http\Middleware\LedgerLogging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum', LedgerLogging::class])->group(function () {
    Route::post('v1/ledger/account/{operation}', [LedgerAccountApiController::class, 'run']);
    Route::post('v1/ledger/root/create', [LedgerCreateApiController::class, 'run']);
    Route::post('v1/ledger/currency/{operation}', [LedgerCurrencyApiController::class, 'run']);
    Route::post('v1/ledger/domain/{operation}', [LedgerDomainApiController::class, 'run']);
    Route::post('v1/ledger/journal/{operation}', [SubJournalApiController::class, 'run']);
    Route::post('v1/ledger/reference/{operation}', [JournalReferenceApiController::class, 'run']);
});
