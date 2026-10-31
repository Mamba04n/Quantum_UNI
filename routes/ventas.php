<?php

use App\Http\Controllers\AdjuntosSeguimientoPedidoController;
use App\Http\Controllers\PdfCotizacionController;
use App\Http\Controllers\PdfPedidoController;
use App\Livewire\Pedidos\OrderStatusShow;
use App\Livewire\Ventas\Cotizaciones\AprobacionCliente\GestorAprobacionCliente;
use App\Livewire\Ventas\Cotizaciones\CorreccionesOrden\GestorCorreccionesOrden;
use App\Livewire\Ventas\Cotizaciones\Kanban\GestorKanban;
use App\Livewire\Ventas\Cotizaciones\LicitacionProveedor\GestorLicitacionProveedor;
use App\Livewire\Ventas\Cotizaciones\OfertaCliente\GestorOfertaCliente;
use App\Livewire\Ventas\Cotizaciones\SolicitudCliente\GestorSolicitudCliente;
use App\Livewire\Ventas\Cotizaciones\SolicitudesCliente\FormularioSolicitudCliente;
use App\Livewire\Ventas\OrderForm;
use App\Livewire\Ventas\OrderStatusBoard;
use App\Livewire\Ventas\UnifiedOrderTracker;
use App\Models\QuotationRequest;
use App\Services\PdfService;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache'])->group(function (): void {
    Route::get('/sales/captacion', \App\Livewire\Crm\AcquisitionBoard::class)->middleware('permission:prospects.view')->name('sales.acquisition');
    Route::get('/sales/tracker', UnifiedOrderTracker::class)->middleware('permission:tracker.view')->name('sales.tracker');
    Route::get('/orders/status', OrderStatusBoard::class)->middleware('permission:order_status.view,tracker.view')->name('orders.status.index');
    Route::get('/orders/status/{order}', OrderStatusShow::class)->middleware('permission:order_status.view,tracker.view')->name('orders.status.show');
    Route::get('/sales/quotations', GestorKanban::class)
        ->middleware('permission:supplier_bid.view,supplier_bid.create,order_approval.view,customer_request.view,client_offer.view')
        ->name('sales.quotations.kanban');
    Route::get('/sales/quotations/corrections', GestorCorreccionesOrden::class)
        ->middleware('permission:client_offer.view,order_approval.view')->name('sales.quotations.corrections');
    Route::get('/sales/quotations/fase-cotizacion/correcciones-orden', GestorCorreccionesOrden::class)
        ->middleware('permission:client_offer.view,order_approval.view')->name('sales.quotations.phase.order-corrections');
    Route::get('/sales/quotations/fase-cotizacion', GestorKanban::class)
        ->middleware('permission:supplier_bid.view,supplier_bid.create,order_approval.view,customer_request.view,client_offer.view')
        ->name('sales.quotations.phase');
    Route::get('/sales/quotations/solicitud/{quoteId?}', fn ($quoteId = null) =>
        redirect()->route('sales.quotations.licitacion', array_filter(['quoteId' => $quoteId])))
        ->name('sales.quotations.solicitud');
    Route::get('/sales/quotations/licitacion/{quoteId?}', GestorLicitacionProveedor::class)
        ->middleware('permission:supplier_bid.view,supplier_bid.create')->name('sales.quotations.licitacion');
    Route::get('/sales/quotations/oferta/{quoteId?}', GestorOfertaCliente::class)
        ->middleware('permission:client_offer.view')->name('sales.quotations.oferta');
    Route::get('/sales/quotations/aprobacion/{quoteId?}', GestorAprobacionCliente::class)
        ->middleware('permission:order_approval.view')->name('sales.quotations.aprobacion');
    Route::get('/sales/quotations/template/excel-items', [\App\Http\Controllers\QuotationItemTemplateController::class, 'download'])
        ->name('sales.quotations.template.excel-items');
    Route::get('/sales/quotations/{quotationRequest}/template/supplier-response/{rfqDocument?}', [\App\Http\Controllers\QuotationItemTemplateController::class, 'downloadSupplierResponse'])
        ->middleware('permission:supplier_bid.view,supplier_bid.create')
        ->name('sales.quotations.template.supplier-response');
    Route::get('/sales/quotations/{quotationRequest}/template/client-offer', [\App\Http\Controllers\QuotationItemTemplateController::class, 'downloadClientOffer'])
        ->middleware('permission:client_offer.view')
        ->name('sales.quotations.template.client-offer');

    Route::get('/sales/offers/{quotation}/preview', function (QuotationRequest $quotation, PdfService $pdfService) {
        abort_unless(auth()->user()?->can('client_offer.preview') || auth()->user()?->can('client_offer_review.view') || auth()->user()?->can('supplier_bid.send_to_approval'), 403);

        $data = $pdfService->clientOfferPreviewData($quotation);
        $companyKey = $quotation->company_issuer ?: 'quantum';
        $view = match ($companyKey) {
            'powersales' => 'pdf.client-offer-powersales-mpdf',
            'marcos_leiva' => 'pdf.client-offer-marcos-leiva-mpdf',
            default => 'pdf.client-offer-mpdf',
        };

        return view($view, $data);
    })->middleware('permission:client_offer.preview,client_offer_review.view,supplier_bid.send_to_approval')->name('sales.offers.preview');

    Route::get('/sales/offers/{quotation}/download-final-pdf', function (QuotationRequest $quotation, PdfService $pdfService) {
        abort_unless(auth()->user()?->can('client_offer.generate_final_pdf') || auth()->user()?->can('supplier_bid.send_to_approval') || auth()->user()?->can('supplier_bid.create'), 403);
        abort_unless($quotation->client_offer_pdf_path, 404);

        return $pdfService->outputStoredPdf($quotation->client_offer_pdf_path, 'Oferta_'.$quotation->reference.'.pdf', true);
    })->middleware('permission:client_offer.generate_final_pdf,supplier_bid.send_to_approval,supplier_bid.create')->name('sales.offers.download-final-pdf');

    Route::get('/sales/direct-orders/create', OrderForm::class)->middleware('permission:direct_order.create,direct_order.create_from_inventory')->name('sales.direct-orders.create');
    Route::get('/sales/solicitud-cliente', FormularioSolicitudCliente::class)->middleware('permission:customer_request.create')->name('sales.customer-request.create');
    Route::get('/sales/quotations/fase-cotizacion/solicitud-cliente', FormularioSolicitudCliente::class)->middleware('permission:customer_request.create')->name('sales.quotations.phase.customer-request');
    Route::get('/sales/quotations/fase-cotizacion/solicitudcliente', FormularioSolicitudCliente::class)->middleware('permission:customer_request.create')->name('sales.quotations.phase.customer-request.alias');
});

Route::middleware(['auth', 'erp.employee', 'permission:direct_order.view,tracker.view_sensitive_documents'])
    ->get('/sales/orders/{order}/pdf', [PdfPedidoController::class, 'generate'])->name('sales.orders.pdf');
Route::middleware('signed')->get('/sales/orders/{order}/client-pdf', [PdfPedidoController::class, 'clientDownload'])->name('sales.orders.client-pdf');
Route::middleware(['auth', 'erp.employee', 'permission:supplier_bid.view,tracker.view_supplier_quotes,internal_approval.view_documents'])
    ->get('/sales/quotations/{id}/pdf', [PdfCotizacionController::class, 'generate'])->name('sales.quotations.pdf');
Route::middleware(['auth', 'erp.employee', 'permission:supplier_bid.view,supplier_bid.create,tracker.view_supplier_quotes,internal_approval.view_documents'])
    ->get('/sales/rfq-documents/{document}/pdf', [PdfCotizacionController::class, 'supplierRfqDocument'])->name('sales.rfq-documents.pdf');
Route::middleware(['auth', 'erp.employee', 'permission:tracker.view,client_offer_review.view'])
    ->get('/tracker/attachments/{attachment}', [AdjuntosSeguimientoPedidoController::class, 'show'])->name('tracker.attachments.show');
