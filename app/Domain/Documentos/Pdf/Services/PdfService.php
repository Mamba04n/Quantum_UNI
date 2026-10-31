<?php

namespace App\Domain\Documentos\Pdf\Services;

use App\Domain\Documentos\Pdf\Support\SupplierRfqDocumentFactory;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\QuotationRequest;
use App\Models\SupplierBid;
use App\Models\SupplierBidResponse;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\ClientOfferDocumentMapper;
use App\Services\ClientOfferReviewService;
use App\Services\ClientOfferVersionService;
use App\Services\CompanyProfileService;
use App\Services\OrderTrackerService;
use App\Services\Pdf\Mappers\SupplierRfqMapper as MpdfSupplierRfqMapper;
use App\Services\Pdf\PdfDocumentService;
use App\Services\SalesWorkflowService;
use App\Services\SupplierRfqDocumentMapper;
use App\Services\SupplierRfqReviewService;
use App\Services\TrackerDocumentService;
use App\Support\PrivateFileResponse;
use Illuminate\Validation\ValidationException;

class PdfService
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly TrackerDocumentService $documentService,
        private readonly ClientOfferVersionService $offerVersionService,
        private readonly CompanyProfileService $companyProfileService,
        private readonly SupplierRfqDocumentMapper $supplierRfqDocumentMapper,
        private readonly PdfDocumentService $pdfDocumentService,
        private readonly MpdfSupplierRfqMapper $mpdfSupplierRfqMapper,
        private readonly ClientOfferDocumentMapper $clientOfferDocumentMapper,
        private readonly SupplierRfqDocumentFactory $supplierRfqDocumentFactory
    ) {}

    public function generateSupplierRfqPdf(QuotationRequest|SupplierBid|SupplierBidResponse|SupplierRfqDocument $supplierBid, ?int $generatedBy = null, ?Entity $supplier = null): string
    {
        $rfqDocument = null;

        if ($supplierBid instanceof SupplierRfqDocument) {
            $supplierBid->loadMissing([
                'quotationRequest.items',
                'quotationRequest.items.product',
                'quotationRequest.items.machine',
                'quotationRequest.items.quotationRequest.clientMachine',
                'quotationRequest.user',
                'quotationRequest.clientMachine',
                'supplier',
            ]);
            $rfqDocument = $supplierBid;
            $request = $supplierBid->quotationRequest;
            $supplier = $supplierBid->supplier;
            app(SupplierRfqReviewService::class)->assertReadyForOfficialRfq($supplierBid);
            if (! in_array($supplierBid->status, [
                SupplierRfqDocument::STATUS_DRAFT,
                SupplierRfqDocument::STATUS_PENDING_ADMIN_REVIEW,
                SupplierRfqDocument::STATUS_ADMIN_CHANGES_REQUESTED,
                SupplierRfqDocument::STATUS_ADMIN_APPROVED,
                SupplierRfqDocument::STATUS_REJECTED,
                SupplierRfqDocument::STATUS_GENERATED,
            ], true)) {
                throw ValidationException::withMessages(['rfq' => 'El RFQ debe estar editable o listo para PDF antes de generar el documento.']);
            }
        } elseif ($supplierBid instanceof SupplierBid || $supplierBid instanceof SupplierBidResponse) {
            $supplierBid->loadMissing([
                'quotationRequest.items',
                'quotationRequest.items.product',
                'quotationRequest.items.machine',
                'quotationRequest.items.quotationRequest.clientMachine',
                'supplier',
            ]);
            $request = $supplierBid->quotationRequest;
            $supplier = $supplierBid->supplier;
        } else {
            $request = $supplierBid;
        }

        if (! $request) {
            throw ValidationException::withMessages(['rfq' => 'No se encontro la solicitud para generar el RFQ.']);
        }

        $request->loadMissing([
            'items',
            'items.product',
            'items.machine',
            'items.quotationRequest.clientMachine',
            'user',
            'clientMachine',
        ]);

        if ($rfqDocument && ! empty($rfqDocument->selected_item_ids)) {
            $selectedItemIds = collect($rfqDocument->selected_item_ids)->map(fn ($id) => (int) $id)->all();
            $request->setRelation('items', $request->items->whereIn('id', $selectedItemIds)->values());
        }

        $documentData = $this->supplierRfqDocumentMapper->map($request);
        if ($rfqDocument) {
            $documentData['rfq_id'] = $rfqDocument->effective_rfq_number;
            $documentData['response_requirements'] = $rfqDocument->effective_response_requirements;
        }
        $mappedItems = $documentData['items'];

        $this->assertSupplierRfqCanBeGenerated($request, $mappedItems);

        $supplierSlug = $supplier ? '_'.str($supplier->name)->slug('_')->limit(40, '') : '';
        $fileName = 'RFQ_'.$request->reference.$supplierSlug.'_'.now()->format('Ymd_His').'.pdf';
        $filePath = 'commercial/rfq/'.$fileName;

        if ($rfqDocument) {
            $this->pdfDocumentService->renderSupplierRfq(
                $this->mpdfSupplierRfqMapper->map($rfqDocument),
                $filePath
            );
        } else {
            $legacyDocument = $this->supplierRfqDocumentFactory->make(
                $request,
                $documentData,
                $supplier
            );

            $this->pdfDocumentService->renderSupplierRfq(
                $this->mpdfSupplierRfqMapper->map($legacyDocument),
                $filePath
            );
        }

        if ($rfqDocument) {
            $generator = auth()->user() ?: ($generatedBy ? User::find($generatedBy) : null);
            if (! $generator) {
                throw ValidationException::withMessages(['rfq' => 'No se pudo resolver el usuario generador del RFQ.']);
            }

            app(SupplierRfqReviewService::class)->markGenerated($rfqDocument, $filePath, $generator);
            $request->forceFill([
                'rfq_pdf_path' => $filePath,
                'rfq_generated_at' => now(),
                'status' => QuotationRequest::STATUS_RFQ_GENERATED,
                'commercial_phase' => SalesWorkflowService::PHASE_SUPPLIER_BID,
            ])->save();
        } else {
            $request->forceFill([
                'rfq_pdf_path' => $filePath,
                'rfq_generated_at' => now(),
            ])->save();
        }

        $tracker = $this->trackerService->trackerForQuotation($request);
        if ($tracker) {
            $this->documentService->attachGeneratedPdfToTracker(
                $tracker,
                $rfqDocument ? 'supplier_rfq_documents' : 'quotation_requests',
                $rfqDocument?->id ?? $request->id,
                'supplier_bid',
                $filePath,
                $fileName,
                $generatedBy,
                'supplier_bid.rfq_pdf_generated',
                $supplier
                    ? "RFQ proveedor generado para {$supplier->name} en solicitud {$request->reference}"
                    : "RFQ proveedor generado para solicitud {$request->reference}"
            );
        }

        ActivityLog::log('ventas', 'supplier_bid.rfq_pdf_generated', $supplier
            ? "RFQ generado para {$supplier->name} en {$request->reference}"
            : "RFQ generado para {$request->reference}", [
                'quote_id' => $request->id,
                'supplier_id' => $supplier->id ?? null,
                'file_path' => $filePath,
                'tracker_id' => $tracker->id ?? null,
            ]);

        return $filePath;
    }

    public function generateClientOfferPdf(QuotationRequest $request, ?int $generatedBy = null): string
    {
        $request->loadMissing([
            'items.product',
            'items.supplierBidSelection.responseItem.response.supplier',
            'user',
        ]);

        if (! in_array($request->status, [
            QuotationRequest::STATUS_APPROVED_TO_SEND,
            QuotationRequest::STATUS_FINAL_PDF_GENERATED,
            QuotationRequest::STATUS_CLIENT_OFFER_GENERATED,
            QuotationRequest::STATUS_OFFER_DRAFT,
            QuotationRequest::STATUS_OFFER_PREVIEW_READY,
            QuotationRequest::STATUS_PENDING_ADMIN_REVIEW,
            QuotationRequest::STATUS_SUPPLIER_RESPONSE_RECEIVED,
            QuotationRequest::STATUS_SUPPLIER_SELECTED,
            QuotationRequest::STATUS_CLIENT_OFFER_SENT,
            QuotationRequest::STATUS_CLIENT_RESPONSE_PENDING,
        ], true)) {
            throw ValidationException::withMessages(['pdf' => 'La oferta no se encuentra en una etapa válida para generar el PDF.']);
        }

        $offerItems = $this->offerItemsForDocument($request);
        $request->setRelation('items', $offerItems->values());

        $this->assertClientOfferHasPrices($request);

        $fileName = 'Oferta_'.$request->reference.'_'.now()->format('Ymd_His').'.pdf';
        $filePath = 'commercial/client-offers/'.$fileName;

        $taxPercent = max(0, (float) ($request->tax_percent ?? 15));
        if ($taxPercent <= 0 && (!isset($request->tax_percent) || $request->tax_percent === null)) {
            $taxPercent = 15;
        }
        $subtotal = (float) $request->items->sum(fn ($item) => (float) $item->line_total);
        $iva = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $iva, 2);
        $mappedItems = $this->clientOfferDocumentMapper->map($request)['items'];

        $companyKey = $request->company_issuer ?: 'quantum';
        $companyData = $this->companyProfileService->getCompanyData($companyKey);
        $footerData = $this->companyProfileService->getFooterData($companyKey);
        $logoBase64 = $this->getCompanyLogoBase64($companyKey);

        $pdfData = [
            'request' => $request,
            'mappedItems' => $mappedItems,
            'subtotal' => $subtotal,
            'iva' => $iva,
            'taxPercent' => $taxPercent,
            'total' => $total,
            'currency' => $request->currency ?? 'C$',
            'exchangeRate' => $request->exchange_rate ?? 'Oficial BCN',
            'warranty' => $request->warranty ?? null,
            'company' => $companyData,
            'footerData' => $footerData,
            'logoBase64' => $logoBase64,
        ];

        $this->pdfDocumentService->renderClientOffer($pdfData, $filePath);

        $request->forceFill([
            'client_offer_pdf_path' => $filePath,
            'client_offer_generated_at' => now(),
            'tax_percent' => $taxPercent,
            'subtotal' => $subtotal,
            'total_amount' => $total,
            'status' => QuotationRequest::STATUS_FINAL_PDF_GENERATED,
            'commercial_phase' => SalesWorkflowService::PHASE_CLIENT_OFFER,
        ])->save();

        $version = $this->offerVersionService->createVersion($request->fresh('items'), auth()->user(), 'PDF final de oferta generado', $filePath, 'final_pdf_generated');

        $tracker = $this->trackerService->trackerForQuotation($request);
        if ($tracker) {
            $this->documentService->attachGeneratedPdfToTracker(
                $tracker,
                'client_offer_versions',
                $version->id,
                'client_offer',
                $filePath,
                $fileName,
                $generatedBy,
                'client_offer.final_pdf_generated',
                "PDF final de Oferta Cliente generado para {$request->reference}"
            );
        }

        ActivityLog::log('ventas', 'client_offer.final_pdf_generated', "PDF final de oferta cliente generado para {$request->reference}", [
            'quote_id' => $request->id,
            'file_path' => $filePath,
            'tracker_id' => $tracker->id ?? null,
        ]);

        return $filePath;
    }

    public function clientOfferPreviewData(QuotationRequest $request): array
    {
        $request->loadMissing([
            'items.product',
            'items.supplierBidSelection.responseItem.response.supplier',
            'user',
        ]);

        app(ClientOfferReviewService::class)->assertOfferComplete($request);

        $offerItems = $this->offerItemsForDocument($request);
        $request->setRelation('items', $offerItems->values());

        $taxPercent = max(0, (float) ($request->tax_percent ?? 0));
        $subtotal = (float) $request->items->sum(fn ($item) => (float) $item->line_total);
        $iva = round($subtotal * ($taxPercent / 100), 2);

        $companyKey = $request->company_issuer ?: 'quantum';
        $companyData = $this->companyProfileService->getCompanyData($companyKey);
        $footerData = $this->companyProfileService->getFooterData($companyKey);
        $logoBase64 = $this->getCompanyLogoBase64($companyKey);

        return [
            'request' => $request,
            'mappedItems' => $this->clientOfferDocumentMapper->map($request)['items'],
            'subtotal' => $subtotal,
            'iva' => $iva,
            'taxPercent' => $taxPercent,
            'total' => round($subtotal + $iva, 2),
            'currency' => $request->currency ?? 'C$',
            'exchangeRate' => $request->exchange_rate ?? 'Oficial BCN',
            'warranty' => $request->warranty ?? null,
            'company' => $companyData,
            'footerData' => $footerData,
            'logoBase64' => $logoBase64,
            'isPreview' => true,
        ];
    }

    public function outputStoredPdf(string $filePath, string $downloadName, bool $download = false)
    {
        return app(PrivateFileResponse::class)->make(
            $filePath,
            request(),
            $downloadName,
            allowInline: ! $download
        );
    }

    private function assertClientOfferHasPrices(QuotationRequest $request): void
    {
        if ($request->items->isEmpty()) {
            throw ValidationException::withMessages(['pdf' => 'No se puede generar oferta cliente sin partidas.']);
        }

        foreach ($request->items as $item) {
            if ((float) ($item->unit_price ?? 0) <= 0) {
                throw ValidationException::withMessages(['pdf' => "La partida {$item->part_number} no tiene precio de venta."]);
            }

            if ((float) ($item->line_total ?? 0) <= 0) {
                throw ValidationException::withMessages(['pdf' => "La partida {$item->part_number} no tiene subtotal valido."]);
            }
        }

        $subtotal = (float) $request->items->sum(fn ($item) => (float) $item->line_total);
        $taxPercent = max(0, (float) ($request->tax_percent ?? 0));
        $iva = round($subtotal * ($taxPercent / 100), 2);
        $total = round($subtotal + $iva, 2);

        if ($subtotal <= 0 || $iva < 0 || $total <= 0) {
            throw ValidationException::withMessages(['pdf' => 'La oferta cliente requiere subtotal, IVA y total validos.']);
        }
    }

    private function offerItemsForDocument(QuotationRequest $request)
    {
        $filtered = $request->items
            ->filter(fn ($item) => (bool) $item->is_approved || (float) $item->line_total > 0)
            ->values();

        return $filtered->isNotEmpty() ? $filtered : $request->items;
    }

    private function assertSupplierRfqCanBeGenerated(QuotationRequest $request, array $mappedItems): void
    {
        if (blank($request->reference)) {
            throw ValidationException::withMessages(['rfq' => 'No se puede generar RFQ sin referencia de solicitud.']);
        }

        if ($request->items->isEmpty()) {
            throw ValidationException::withMessages(['rfq' => 'No se puede generar RFQ sin partidas.']);
        }

        foreach ($mappedItems as $index => $item) {
            $line = $index + 1;

            if (($item['part_name'] ?? '-') === '-') {
                throw ValidationException::withMessages(['rfq' => "La partida {$line} no tiene nombre de pieza ni descripcion."]);
            }

            if (($item['reference'] ?? '-') === '-') {
                throw ValidationException::withMessages(['rfq' => "La partida {$line} no tiene referencia, numero de parte o serie."]);
            }

            if ((int) ($item['quantity'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['rfq' => "La partida {$line} no tiene cantidad valida."]);
            }
        }
    }

    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    public function getCompanyLogoBase64(?string $companyKey = null): ?string
    {
        $companyData = $this->companyProfileService->getCompanyData($companyKey);
        $path = $companyData['logo_path'] ?? null;

        if ($path && file_exists($path)) {
            $mime = mime_content_type($path) ?: 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
        }

        $logoPath = public_path('images/logo-email.png');

        if (file_exists($logoPath)) {
            return 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));
        }

        $fallbackPath = public_path('images/logo-quantum.png');

        if (file_exists($fallbackPath)) {
            return 'data:image/png;base64,'.base64_encode(file_get_contents($fallbackPath));
        }

        return null;
    }
}
