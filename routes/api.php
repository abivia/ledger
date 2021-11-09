<?php

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
    Route::post('v1/ledger/account/add', [LedgerAccountApiController::class, 'add']);
    Route::post('v1/ledger/account/delete', [LedgerAccountApiController::class, 'delete']);
    Route::post('v1/ledger/account/get', [LedgerAccountApiController::class, 'get']);
    Route::post('v1/ledger/account/update', [LedgerAccountApiController::class, 'update']);

    Route::post('v1/ledger/root/create', [LedgerCreateApiController::class, 'run']);

    Route::post('v1/ledger/currency/add', [LedgerCurrencyApiController::class, 'add']);
    Route::post('v1/ledger/currency/delete', [LedgerCurrencyApiController::class, 'delete']);
    Route::post('v1/ledger/currency/get', [LedgerCurrencyApiController::class, 'get']);
    Route::post('v1/ledger/currency/update', [LedgerCurrencyApiController::class, 'update']);

    Route::post('v1/ledger/domain/add', [LedgerDomainApiController::class, 'add']);
    Route::post('v1/ledger/domain/delete', [LedgerDomainApiController::class, 'delete']);
    Route::post('v1/ledger/domain/get', [LedgerDomainApiController::class, 'get']);
    Route::post('v1/ledger/domain/update', [LedgerDomainApiController::class, 'update']);

    Route::post('v1/ledger/journal/add', [SubJournalApiController::class, 'add']);
    Route::post('v1/ledger/journal/delete', [SubJournalApiController::class, 'delete']);
    Route::post('v1/ledger/journal/get', [SubJournalApiController::class, 'get']);
    Route::post('v1/ledger/journal/update', [SubJournalApiController::class, 'update']);
});
