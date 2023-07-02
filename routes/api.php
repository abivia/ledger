<?php

use Abivia\Ledger\Http\Controllers\Api\BatchApiController;
use Abivia\Ledger\Http\Controllers\Api\JournalEntryApiController;
use Abivia\Ledger\Http\Controllers\Api\JournalReferenceApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerAccountApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerBalanceApiController;
use Abivia\Ledger\Http\Controllers\Api\ReportApiController;
use Abivia\Ledger\Http\Controllers\Api\RootApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerCurrencyApiController;
use Abivia\Ledger\Http\Controllers\Api\LedgerDomainApiController;
use Abivia\Ledger\Http\Controllers\Api\SubJournalApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('account/{operation}', [LedgerAccountApiController::class, 'run']);
Route::post('balance/{operation}', [LedgerBalanceApiController::class, 'run']);
Route::post('batch', [BatchApiController::class, 'run']);
Route::post('currency/{operation}', [LedgerCurrencyApiController::class, 'run']);
Route::post('domain/{operation}', [LedgerDomainApiController::class, 'run']);
Route::post('entry/{operation}', [JournalEntryApiController::class, 'run']);
Route::post('journal/{operation}', [SubJournalApiController::class, 'run']);
Route::post('reference/{operation}', [JournalReferenceApiController::class, 'run']);
Route::post('report', [ReportApiController::class, 'run']);
Route::get('root/templates', [RootApiController::class, 'templates']);
Route::post('root/{operation}', [RootApiController::class, 'run']);
