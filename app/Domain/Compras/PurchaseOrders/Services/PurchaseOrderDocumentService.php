<?php

namespace App\Domain\Compras\PurchaseOrders\Services;

use App\Models\ActivityLog;
use App\Models\ApprovalRequest;
use App\Models\OrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDocument;
use App\Models\QuotationRequest;
use App\Models\User;
use App\Services\CompanyProfileService;
use App\Services\OrderStatusService;
use App\Services\Pdf\PdfDocumentService;
use App\Services\PurchaseOrderProviderDocumentMapper;
use App\Services\TrackerDocumentService;
use Illuminate\Validation\ValidationException;

class PurchaseOrderDocumentService
{
    public function __construct(
        private readonly TrackerDocumentService $trackerDocumentService,
        private readonly OrderStatusService $orderStatusService,
        private readonly CompanyProfileService $companyProfileService,
        private readonly PurchaseOrderProviderDocumentMapper $mapper,
        private readonly PdfDocumentService $pdfDocumentService
    ) {}

    public function documentForApproval(ApprovalRequest $approval): PurchaseOrderDocument
    {
        $purchaseOrder = $approval->purchaseOrders()->orderBy('id')->first();

        if (! $purchaseOrder) {
            throw ValidationException::withMessages(['purchase_order' => 'No hay una orden de compra generada para esta aprobación.']);
        }

        return $this->documentForPurchaseOrder($approval, $purchaseOrder);
    }

    public function documentForPurchaseOrder(ApprovalRequest $approval, PurchaseOrder $purchaseOrder): PurchaseOrderDocument
    {
        $quotation = $this->quotationForApproval($approval);

        return PurchaseOrderDocument::firstOrCreate(
            [
                'approval_request_id' => $approval->id,
                'purchase_order_id' => $purchaseOrder->id,
            ],
            [
                'sale_order_id' => $quotation?->sale_order_id,
                'quote_to_name' => 'Quantum Soluciones S.A.',
                'consignee_name' => $quotation?->consignee_name ?: 'Quantum Soluciones S.A.',
                'consignee_comment' => $quotation?->consignee_handling_notes,
                'consignee_address_1' => $quotation?->consignee_address ?: 'Managua, Nicaragua',
                'consignee_country' => 'Nicaragua',
                'consignee_city' => 'Managua',
                'consignee_phone' => $quotation?->user?->phone ?: '+505 8326 2783',
                'consignee_emails' => $quotation?->quote_to_email ?: 'operaciones@quantumsolucionesnic.com',
                'shipping_method' => 'Courier',
                'shipping_terms' => 'Segun confirmacion proveedor',
                'incoterm' => 'FOB',
                'estimated_arrival_date' => $this->approvedItems($approval, $purchaseOrder)->pluck('estimated_arrival_date')->filter()->sort()->first(),
                'supplier_comments' => 'Favor confirmar disponibilidad, tiempo de despacho y garantia.',
                'document_footer_comments' => 'Documento generado por Quantum Soluciones para control administrativo de compra.',
            ]
        );
    }

    public function documentForDirectPurchaseOrder(PurchaseOrder $purchaseOrder): PurchaseOrderDocument
    {
        return PurchaseOrderDocument::firstOrCreate(
            ['purchase_order_id' => $purchaseOrder->id],
            [
                'quote_to_name' => 'Quantum Soluciones S.A.',
                'consignee_name' => 'Quantum Soluciones S.A.',
                'consignee_address_1' => 'Managua, Nicaragua',
                'consignee_country' => 'Nicaragua',
                'consignee_city' => 'Managua',
                'consignee_phone' => '+505 8326 2783',
                'consignee_emails' => 'operaciones@quantumsolucionesnic.com',
                'shipping_method' => 'Courier',
                'shipping_terms' => 'Según confirmación del proveedor',
                'incoterm' => 'FOB',
                'estimated_arrival_date' => $purchaseOrder->expected_date,
                'supplier_comments' => 'Favor confirmar disponibilidad, tiempo de despacho y garantía.',
                'document_footer_comments' => 'Documento generado por Quantum Soluciones para control administrativo de compra.',
            ]
        );
    }

    public function generatePdf(PurchaseOrderDocument $document, ?User $user = null, bool $preview = false): string
    {
        $user = $user ?: auth()->user();
        $approval = $document->approvalRequest()->with('tracker')->first();
        if ($approval && $approval->status !== ApprovalRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages(['approval' => 'La aprobacion interna debe estar aprobada antes de generar la OC proveedor.']);
        }
        $this->assertDocumentDataIsComplete($document);

        $purchaseOrder = $document->purchaseOrder()->with(['supplier', 'items'])->firstOrFail();
        $items = $this->approvedItems($approval, $purchaseOrder);

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'No hay productos aprobados o seleccionados para compra.']);
        }

        foreach ($items as $item) {
            if ((float) $item['quantity'] <= 0) {
                throw ValidationException::withMessages(['items' => "La partida {$item['part_number']} no tiene cantidad valida."]);
            }
            if ((float) $item['unit_cost'] <= 0) {
                throw ValidationException::withMessages(['items' => "La partida {$item['part_number']} no tiene precio proveedor."]);
            }
            if (blank($item['supplier_name'])) {
                throw ValidationException::withMessages(['items' => "La partida {$item['part_number']} no tiene proveedor asignado."]);
            }
        }

        $quotation = $approval ? $this->quotationForApproval($approval) : null;
        $data = $this->mapper->map($document, $approval, $quotation, $purchaseOrder, $items, $user);
        $orderCode = str($data['document']['code'] ?? 'OC-'.($approval?->id ?? $purchaseOrder->id))->slug('_')->upper()->toString();
        $fileName = 'OC_'.$orderCode.'_'.now()->format('Ymd_His').'.pdf';
        $filePath = 'purchase-order-documents/'.$fileName;

        $pdfData = [
            'document' => $document,
            'approval' => $approval,
            'quotation' => $quotation,
            'data' => $data,
            'items' => $data['items'],
            'totals' => $data['totals'],
            'currency' => $data['totals']['currency'],
            'company' => $this->companyProfileService->getCompanyData(),
            'footerData' => $this->companyProfileService->getFooterData(),
            'logoBase64' => $this->logoBase64(),
            'generatedBy' => $user,
        ];

        $this->pdfDocumentService->renderPurchaseOrderProvider($pdfData, $filePath);

        if (! $preview) {
            $orderStatus = $this->resolveOrderStatus($document, $approval);

            $document->forceFill([
                'status' => $document->pdf_path ? 'regenerated' : 'generated',
                'pdf_path' => $filePath,
                'order_status_id' => $orderStatus?->id,
                'generated_by' => $user?->id,
                'generated_at' => now(),
            ])->save();

            if ($orderStatus) {
                $this->ensureAttachedToOrderStatus($document, $orderStatus, $user);
            }

            if ($approval?->tracker) {
                $this->trackerDocumentService->attachGeneratedPdfToTracker(
                    $approval->tracker,
                    'purchase_order_documents',
                    $document->id,
                    'pedido_extranjero',
                    $filePath,
                    $fileName,
                    $user?->id,
                    'purchase_order_provider.pdf_generated',
                    "Orden de compra proveedor generada para aprobación #{$approval->id}"
                );
            }

            $activityDescription = $approval
                ? "Orden de compra proveedor generada para aprobación #{$approval->id}"
                : "Orden de compra directa generada para orden #{$purchaseOrder->id}";

            ActivityLog::log('compras', 'purchase_order_provider.pdf_generated', $activityDescription, [
                'approval_request_id' => $approval?->id,
                'purchase_order_id' => $purchaseOrder->id,
                'purchase_order_document_id' => $document->id,
                'pdf_path' => $filePath,
            ]);
        }

        return $filePath;
    }

    public function markSentToProvider(PurchaseOrderDocument $document, ?User $user = null, ?string $notes = null): PurchaseOrderDocument
    {
        $user = $user ?: auth()->user();
        $document = $document->fresh(['orderStatus', 'approvalRequest', 'purchaseOrder']);

        if (blank($document->pdf_path)) {
            throw ValidationException::withMessages(['document' => 'Primero debe existir el PDF de la OC proveedor.']);
        }

        $orderStatus = $document->orderStatus ?: $this->resolveOrderStatus($document, $document->approvalRequest);

        if (! $orderStatus) {
            throw ValidationException::withMessages(['order_status' => 'No se encontro estado del pedido para esta orden.']);
        }

        $this->ensureAttachedToOrderStatus($document, $orderStatus, $user);
        $this->orderStatusService->markProviderPurchaseOrderSent(
            $orderStatus,
            $user,
            $notes,
            $document->purchaseOrder
        );

        $document->forceFill([
            'status' => 'sent_to_provider',
            'order_status_id' => $orderStatus->id,
            'sent_to_provider_at' => now(),
            'sent_to_provider_by' => $user?->id,
        ])->save();

        ActivityLog::log('compras', 'purchase_order_provider.sent_to_provider', "OC proveedor marcada como enviada para documento #{$document->id}", [
            'purchase_order_document_id' => $document->id,
            'order_status_id' => $orderStatus->id,
        ]);

        return $document->fresh();
    }

    private function ensureAttachedToOrderStatus(PurchaseOrderDocument $document, OrderStatus $orderStatus, ?User $user): void
    {
        $exists = $orderStatus->documents()
            ->where('document_type', 'purchase_order_provider_pdf')
            ->where('file_path', $document->pdf_path)
            ->exists();

        if ($exists) {
            return;
        }

        $this->orderStatusService->attachDocument(
            $orderStatus,
            null,
            OrderStatusService::PHASE_PURCHASE,
            'purchase_order_provider_pdf',
            $document->pdf_path,
            $user,
            'Orden de compra proveedor / pedido extranjero.'
        );
    }

    public function approvedItems(?ApprovalRequest $approval, ?PurchaseOrder $purchaseOrder = null)
    {
        if ($purchaseOrder) {
            $purchaseOrder->loadMissing(['supplier', 'items']);

            return $purchaseOrder->items->map(fn ($item) => [
                'part_number' => $item->part_number,
                'part_name' => $item->part_name,
                'description' => $item->notes,
                'brand' => $item->brand,
                'model' => $item->model,
                'serial_number' => null,
                'quantity' => (float) $item->quantity,
                'unit_cost' => (float) $item->unit_price,
                'currency' => $item->currency ?: $purchaseOrder->currency ?: 'USD',
                'subtotal' => (float) $item->total_price,
                'estimated_arrival_date' => $item->expected_date,
                'supplier_name' => $purchaseOrder->supplier?->name,
            ])->values();
        }

        $quotation = $this->quotationForApproval($approval);

        if (! $quotation) {
            return collect();
        }

        $quotation->loadMissing([
            'items',
            'supplierBidSelections.supplier',
            'supplierBidSelections.quotationItem',
            'supplierBidSelections.responseItem',
        ]);

        $selected = $quotation->supplierBidSelections
            ->filter(fn ($selection) => (float) $selection->selected_quantity > 0 && (float) $selection->selected_unit_cost > 0)
            ->values();

        if ($selected->isNotEmpty()) {
            return $selected->map(function ($selection) {
                $quoteItem = $selection->quotationItem;
                $responseItem = $selection->responseItem;

                return [
                    'part_number' => $responseItem?->quoted_part_number ?: $quoteItem?->part_number,
                    'part_name' => $responseItem?->quoted_description ?: $quoteItem?->part_name ?: $quoteItem?->description,
                    'description' => $quoteItem?->description,
                    'brand' => $responseItem?->quoted_brand ?: $quoteItem?->brand,
                    'model' => $responseItem?->quoted_model ?: $quoteItem?->model,
                    'serial_number' => $responseItem?->quoted_serial_number ?: $responseItem?->quoted_serial,
                    'quantity' => (float) $selection->selected_quantity,
                    'unit_cost' => (float) $selection->selected_unit_cost,
                    'currency' => $selection->currency ?: 'USD',
                    'subtotal' => round((float) $selection->selected_quantity * (float) $selection->selected_unit_cost, 2),
                    'estimated_arrival_date' => $selection->estimated_arrival_date,
                    'supplier_name' => $selection->supplier?->name,
                ];
            });
        }

        return $quotation->items
            ->filter(fn ($item) => $item->is_approved || in_array($item->status, ['approved', 'quoted', 'pending_review'], true))
            ->map(fn ($item) => [
                'part_number' => $item->part_number,
                'part_name' => $item->part_name ?: $item->description,
                'description' => $item->description,
                'brand' => $item->brand,
                'model' => $item->model,
                'serial_number' => null,
                'quantity' => (float) $item->quantity,
                'unit_cost' => (float) ($item->supplier_unit_cost ?? $item->unit_cost ?? $item->unit_price ?? 0),
                'currency' => 'USD',
                'subtotal' => round((float) $item->quantity * (float) ($item->supplier_unit_cost ?? $item->unit_cost ?? $item->unit_price ?? 0), 2),
                'estimated_arrival_date' => null,
                'supplier_name' => $item->supplier_name ?? null,
            ])->values();
    }

    private function assertDocumentDataIsComplete(PurchaseOrderDocument $document): void
    {
        foreach ([
            'quote_to_name' => 'Cotizar / ordenar a nombre de',
            'consignee_name' => 'Nombre de consignatario',
            'consignee_address_1' => 'Direccion 1',
            'consignee_country' => 'Pais',
            'consignee_city' => 'Ciudad',
            'consignee_phone' => 'Telefono',
        ] as $field => $label) {
            if (blank($document->{$field})) {
                throw ValidationException::withMessages([$field => "El campo {$label} es obligatorio para generar la OC proveedor."]);
            }
        }

        $emails = collect(explode(',', (string) $document->consignee_emails))->map(fn ($email) => trim($email))->filter();
        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['consignee_emails' => "Correo invalido: {$email}"]);
            }
        }
    }

    public function quotationForApproval(ApprovalRequest $approval): ?QuotationRequest
    {
        if ($approval->source_module && $approval->source_module !== 'quotation_requests') {
            return null;
        }

        return QuotationRequest::with('saleOrder.orderStatus')->find($approval->source_id);
    }

    private function resolveOrderStatus(PurchaseOrderDocument $document, ?ApprovalRequest $approval): ?OrderStatus
    {
        if ($document->order_status_id) {
            return OrderStatus::find($document->order_status_id);
        }

        $quotation = $approval ? $this->quotationForApproval($approval) : null;

        if (! $quotation?->saleOrder) {
            return null;
        }

        return $this->orderStatusService->ensureForSaleOrder($quotation->saleOrder, auth()->user());
    }

    private function logoBase64(): ?string
    {
        $path = public_path('images/logo-email.png');

        if (! is_file($path)) {
            $path = public_path('images/logo-quantum.png');
        }

        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }
}
