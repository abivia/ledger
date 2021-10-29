<?php

use App\Http\Controllers\LedgerAccount\InitializeController;
use App\Http\Controllers\LedgerAccount\AddController;
use App\Http\Controllers\LedgerAccountController;
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
    Route::post('v1/ledger/account/add', [AddController::class, 'run']);
    Route::post('v1/ledger/account/get', [LedgerAccountController::class, 'get']);
    Route::post('v1/ledger/create', [InitializeController::class, 'run']);
});
