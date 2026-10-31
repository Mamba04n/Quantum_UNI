<?php

use App\Http\Controllers\AdjuntosPrivadosController;
use App\Livewire\Compras\PurchaseOrderForm;
use App\Models\PurchaseOrderDocument;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache', 'permission:supplier_bid.view_costs'])->group(function (): void {
    Route::get('/proveedores/precios', \App\Livewire\Compras\SupplierPriceBook::class)->name('purchases.supplier-prices');
    Route::get('/proveedores/precios/plantilla', [\App\Http\Controllers\SupplierPriceDocumentController::class, 'template'])->name('purchases.supplier-prices.template');
    Route::get('/proveedores/precios/{quotation}/archivo', [\App\Http\Controllers\SupplierPriceDocumentController::class, 'download'])->name('purchases.supplier-prices.document');
});

Route::middleware(['auth', 'erp.employee', 'no-cache', 'permission:purchases.order.create,purchase_order.view,purchase_order.create'])
    ->get('/purchases/create', PurchaseOrderForm::class)->name('purchases.create');

Route::middleware(['auth', 'erp.employee', 'permission:purchase_order_provider.view,purchase_order_provider.download_pdf,purchase_order_document.view'])
    ->get('/purchase-order-documents/{document}/pdf', function (PurchaseOrderDocument $document, Request $request, PdfService $pdfService) {
        abort_if(! $document->pdf_path, 404);

        return $pdfService->outputStoredPdf($document->pdf_path, 'OC_Proveedor_'.$document->id.'.pdf', $request->boolean('download'));
    })->name('purchase-order-documents.pdf');

Route::middleware(['auth', 'erp.employee', 'permission:order_status.view,tracker.view'])
    ->get('/order-status-documents/{document}/download', [AdjuntosPrivadosController::class, 'orderStatusDocument'])
    ->name('order-status-documents.download');
