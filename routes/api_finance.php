<?php

use App\Http\Controllers\Api\V1\Finance\AccountPayableController;
use App\Http\Controllers\Api\V1\Finance\AccountReceivableController;
use App\Http\Controllers\Api\V1\Finance\ExchangeRateController;
use App\Http\Controllers\Api\V1\Finance\FinanceDocumentController;
use App\Http\Controllers\Api\V1\Finance\FinanceTokenController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Finance\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/finance')->name('api.v1.finance.')->group(function (): void {
    Route::post('/tokens', [FinanceTokenController::class, 'store'])
        ->middleware('throttle:finance-token')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'finance.api-user', 'throttle:finance-api', 'finance.api-audit'])->group(function (): void {
        Route::get('/tokens', [FinanceTokenController::class, 'index'])
            ->middleware('abilities:finance:tokens')->name('tokens.index');
        Route::delete('/tokens', [FinanceTokenController::class, 'destroyAll'])
            ->middleware('abilities:finance:tokens')->name('tokens.destroy-all');
        Route::delete('/tokens/{deviceId}', [FinanceTokenController::class, 'destroy'])
            ->middleware('abilities:finance:tokens')->whereUuid('deviceId')->name('tokens.destroy');

        Route::apiResource('receivables', AccountReceivableController::class)
            ->only(['index', 'show'])->middleware('abilities:finance:receivables:read');
        Route::apiResource('payables', AccountPayableController::class)
            ->only(['index', 'show'])->middleware('abilities:finance:payables:read');
        Route::apiResource('invoices', InvoiceController::class)
            ->only(['index', 'show'])->middleware('abilities:finance:invoices:read');
        Route::apiResource('payments', PaymentController::class)
            ->only(['index', 'show'])->middleware('abilities:finance:payments:read');
        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])
            ->middleware('abilities:finance:rates:read')->name('exchange-rates.index');
        Route::get('/documents/{type}/{id}', FinanceDocumentController::class)
            ->middleware('abilities:finance:documents:read')
            ->whereIn('type', ['cxc', 'cxp', 'invoice', 'payment'])
            ->whereNumber('id')
            ->name('documents.show');
    });
});
