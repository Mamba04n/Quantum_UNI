<?php

use App\Http\Controllers\AdjuntosPrivadosController;
use Illuminate\Support\Facades\Route;

// Rutas de imágenes protegidas con autenticación y soporte de caché en navegador
Route::middleware(['auth'])->group(function (): void {
    Route::get('/private/quotation-cover/{quotationRequest}', \App\Http\Controllers\QuotationCoverController::class)->name('private.quotation-cover');
    Route::get('/private/client-machines/{machine}/image', [AdjuntosPrivadosController::class, 'clientMachineImage'])->name('private.client-machine-image');
});

// Rutas de documentos y adjuntos privados sin caché
Route::middleware(['auth', 'no-cache'])->group(function (): void {
    Route::get('/private/customer-request-evidence/{quotationRequest}', [AdjuntosPrivadosController::class, 'customerRequestEvidence'])->name('private.customer-request-evidence');
    Route::get('/private/stock-moves/{stockMove}/attachment', [AdjuntosPrivadosController::class, 'stockMoveAttachment'])->name('private.stock-move-attachment');
    Route::get('/private/stock-move-attachments/{attachment}', [AdjuntosPrivadosController::class, 'stockMoveAttachmentFile'])->name('private.stock-move-attachment-file');
    Route::get('/private/ticket-messages/{message}/attachment', [AdjuntosPrivadosController::class, 'ticketMessageAttachment'])->name('private.ticket-message-attachment');
});

