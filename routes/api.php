<?php

use App\Http\Controllers\Api\LedgerAccountApiController;
use App\Http\Controllers\Api\LedgerCreateApiController;
use App\Http\Controllers\Api\LedgerCurrencyApiController;
use App\Http\Controllers\Api\LedgerDomainApiController;
use App\Http\Controllers\LedgerAccount\AddController;
use App\Http\Controllers\LedgerAccountController;
use App\Http\Controllers\LedgerCurrencyController;
use App\Http\Middleware\LedgerLogging;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware(['auth:sanctum', LedgerLogging::class])->group(function () {
    Route::post('v1/ledger/account/add', [LedgerAccountApiController::class, 'add']);
    Route::post('v1/ledger/account/delete', [LedgerAccountApiController::class, 'delete']);
    Route::post('v1/ledger/account/get', [LedgerAccountApiController::class, 'get']);
    Route::post('v1/ledger/account/update', [LedgerAccountApiController::class, 'update']);
    Route::post('v1/ledger/create', [LedgerCreateApiController::class, 'run']);

    Route::post('v1/ledger/currency/add', [LedgerCurrencyApiController::class, 'add']);
    Route::post('v1/ledger/currency/delete', [LedgerCurrencyApiController::class, 'delete']);
    Route::post('v1/ledger/currency/get', [LedgerCurrencyApiController::class, 'get']);
    Route::post('v1/ledger/currency/update', [LedgerCurrencyApiController::class, 'update']);
});
