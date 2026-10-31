<?php

use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\PublicTrackingController;
use App\Livewire\Finanzas\PaymentLinkManager;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache', 'permission:payment_links.view'])->get('/finance/payment-links', PaymentLinkManager::class)->name('finance.payment-links');
Route::middleware(['throttle:30,1', 'no-cache'])->group(function () {
    Route::get('/pagar/{token}', [PublicPaymentController::class, 'show'])->name('public.payment.show');
    Route::post('/pagar/{token}/paypal/order', [PublicPaymentController::class, 'checkout'])->name('public.payment.order');
    Route::post('/pagar/{token}/paypal/capture', [PublicPaymentController::class, 'capture'])->name('public.payment.capture');
    Route::get('/seguimiento/{code?}', [PublicTrackingController::class, 'show'])->name('public.tracking');
});
Route::post('/webhooks/paypal', [PublicPaymentController::class, 'webhook'])->middleware('throttle:120,1')->name('public.paypal.webhook');
