<?php

use App\Http\Controllers\DocumentoFinanzasController;
use App\Http\Controllers\PdfFacturaController;
use App\Livewire\Finanzas\AccountPayableDashboard;
use App\Livewire\Finanzas\AccountReceivableDashboard;
use App\Livewire\Finanzas\ExchangeRateManager;
use App\Livewire\Finanzas\FinanceHub;
use App\Livewire\Finanzas\InvoiceDashboard;
use App\Livewire\Finanzas\PaymentGateway;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache', 'permission:finance.view'])->group(function (): void {
    Route::get('/finance/hub', FinanceHub::class)->name('finance.hub');
    Route::get('/finance/cxp', AccountPayableDashboard::class)->name('finance.cxp');
    Route::get('/finance/cxc', AccountReceivableDashboard::class)->name('finance.cxc');
    Route::get('/finance/payments', PaymentGateway::class)->name('finance.payments');
    Route::get('/finance/invoices', InvoiceDashboard::class)->name('finance.invoices');
    Route::get('/finance/exchange-rates', ExchangeRateManager::class)
        ->name('finance.exchange-rates');
});

Route::middleware(['auth', 'erp.employee'])
    ->get('/finance/invoices/preprinted-preview', [PdfFacturaController::class, 'preprintedPreview'])->name('finance.invoices.preprinted-preview');
Route::middleware(['auth', 'erp.employee', 'permission:finance.invoice.view,tracker.view_invoices'])
    ->get('/finance/invoices/{invoice}/pdf', [PdfFacturaController::class, 'generate'])->name('finance.invoices.pdf');
Route::middleware('signed')->get('/finance/invoices/{invoice}/client-pdf', [PdfFacturaController::class, 'clientDownload'])->name('finance.invoices.client-pdf');
Route::middleware(['auth', 'erp.employee'])->get('/finance/documents/{type}/{id}/download', [DocumentoFinanzasController::class, 'download'])->name('finance.documents.download');
Route::middleware(['auth', 'erp.employee'])->get('/finance/change-requests/{changeRequest}/download-support', [DocumentoFinanzasController::class, 'downloadChangeRequestSupport'])->name('finance.change-requests.download-support');
