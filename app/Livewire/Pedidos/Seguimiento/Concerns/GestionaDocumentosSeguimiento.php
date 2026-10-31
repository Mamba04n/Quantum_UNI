<?php

namespace App\Livewire\Pedidos\Seguimiento\Concerns;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\OrderStatusItem;
use App\Models\Payment;
use App\Models\PurchaseOrderDocument;
use App\Services\OrderStatusService;
use App\Services\PurchaseOrderDocumentService;
use Illuminate\Validation\ValidationException;

trait GestionaDocumentosSeguimiento
{
    public function attachDocument(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('supplier_confirmation.upload') || auth()->user()?->can('shipment.upload') || auth()->user()?->can('logistics.upload_document'), 403);

        $this->storePhaseDocument($service);
        $this->refreshStatus('Documento adjuntado.');
    }

    public function markPurchaseOrderSent(int $documentId, PurchaseOrderDocumentService $service): void
    {
        abort_unless(auth()->user()?->can('order_status.manage') || auth()->user()?->can('purchase_order_provider.edit') || auth()->user()?->can('purchase_order_document.edit'), 403);

        $document = PurchaseOrderDocument::where('order_status_id', $this->orderStatus->id)->findOrFail($documentId);
        $service->markSentToProvider($document, auth()->user(), $this->notes ?: null);
        $this->refreshStatus('Orden de compra marcada como enviada al proveedor.');
    }

    private function financialDocuments()
    {
        $cxp = AccountPayable::query()
            ->where('order_status_id', $this->orderStatus->id)
            ->get()
            ->map(fn ($item) => [
                'type' => 'CXP',
                'number' => $item->payable_code ?: $item->invoice_number ?: '#'.$item->id,
                'amount' => $item->total_amount,
                'date' => $item->created_at,
                'user' => $item->creator?->name ?? 'Finanzas',
                'url' => $item->support_document_path ? route('finance.documents.download', ['type' => 'cxp', 'id' => $item->id]) : null,
            ]);

        $cxc = AccountReceivable::query()
            ->where('order_status_id', $this->orderStatus->id)
            ->get()
            ->map(fn ($item) => [
                'type' => 'CXC',
                'number' => $item->receivable_code ?: $item->invoice_number ?: '#'.$item->id,
                'amount' => $item->total_amount,
                'currency' => $item->currency ?: 'NIO',
                'amount_original' => $item->amount_usd,
                'original_currency' => $item->amount_usd !== null ? 'USD' : null,
                'exchange_rate' => $item->exchange_rate,
                'date' => $item->created_at,
                'user' => $item->creator?->name ?? 'Finanzas',
                'url' => $item->support_document_path ? route('finance.documents.download', ['type' => 'cxc', 'id' => $item->id]) : null,
            ]);

        $invoices = Invoice::query()
            ->where('order_status_id', $this->orderStatus->id)
            ->get()
            ->map(fn ($item) => [
                'type' => 'Factura',
                'number' => $item->invoice_number ?: '#'.$item->id,
                'amount' => $item->total ?: $item->amount_nio,
                'date' => $item->date,
                'user' => $item->uploader?->name ?? 'Finanzas',
                'url' => $item->invoice_file_path ? route('finance.documents.download', ['type' => 'invoice', 'id' => $item->id]) : null,
            ]);

        $payments = Payment::query()
            ->whereHas('accountReceivable', fn ($query) => $query->where('order_status_id', $this->orderStatus->id))
            ->orWhereHas('accountPayable', fn ($query) => $query->where('order_status_id', $this->orderStatus->id))
            ->get()
            ->map(fn ($item) => [
                'type' => 'Pago',
                'number' => $item->payment_code ?: $item->payment_reference ?: '#'.$item->id,
                'amount' => $item->amount,
                'date' => $item->payment_date,
                'user' => $item->registrar?->name ?? 'Finanzas',
                'url' => $item->payment_evidence_path ? route('finance.documents.download', ['type' => 'payment', 'id' => $item->id]) : null,
            ]);

        return $cxp->concat($cxc)->concat($invoices)->concat($payments)->sortByDesc('date')->values();
    }

    private function purchaseOrderDocuments()
    {
        return $this->orderStatus->purchaseOrderDocuments
            ->filter(fn (PurchaseOrderDocument $document) => filled($document->purchase_order_id))
            ->map(fn (PurchaseOrderDocument $document) => [
                'id' => $document->id,
                'code' => $document->purchaseOrder?->reference_number ?: ($document->approvalRequest?->approval_code ?: 'OC proveedor #'.$document->id),
                'supplier' => $document->purchaseOrder?->supplier?->name ?: 'Proveedor sin nombre',
                'status' => $document->status,
                'generated_at' => $document->generated_at,
                'sent_at' => $document->sent_to_provider_at,
                'sender' => $document->sender?->name,
                'view_url' => $document->pdf_path ? route('purchase-order-documents.pdf', $document) : null,
                'download_url' => $document->pdf_path ? route('purchase-order-documents.pdf', ['document' => $document, 'download' => 1]) : null,
                'edit_url' => $document->approval_request_id ? route('admin.approvals.purchase-document', $document->approval_request_id) : null,
            ]);
    }

    private function statusDocuments()
    {
        return $this->orderStatus->documents
            ->sortByDesc('created_at')
            ->values()
            ->map(fn ($document) => [
                'id' => $document->id,
                'name' => $document->file_name,
                'type' => $document->document_type,
                'phase' => $document->phase,
                'date' => $document->created_at,
                'user' => $document->uploader?->name ?: $document->generator?->name,
                'item_id' => $document->order_status_item_id,
                'item_label' => $document->item?->part_number ?: $document->item?->part_name,
                'view_url' => route('order-status-documents.download', $document),
                'download_url' => route('order-status-documents.download', ['document' => $document, 'download' => 1]),
                'is_image' => in_array(strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true),
                'preview_url' => route('order-status-documents.download', $document).'?v='.optional($document->updated_at ?: $document->created_at)->timestamp,
            ]);
    }

    public function hasRequiredPhaseDocument(string $documentType, ?int $itemId = null): bool
    {
        $accepted = $this->acceptedPhaseDocumentTypes($documentType);

        return $this->orderStatus->documents
            ->whereIn('document_type', $accepted)
            ->filter(function ($document) use ($documentType, $itemId) {
                if ($this->documentBelongsToOrder($documentType)) {
                    return true;
                }

                return $document->order_status_item_id === null || $document->order_status_item_id === $itemId;
            })
            ->isNotEmpty();
    }

    private function assertRequiredPhaseDocument(string $documentType, ?int $itemId = null): void
    {
        if (in_array($this->activePhase, [OrderStatusService::PHASE_WAREHOUSE], true)) {
            if (! $this->hasRequiredPhaseDocument($documentType, $itemId)) {
                $label = $this->phaseDefinitions()[$this->activePhase]['required'][$documentType] ?? str($documentType)->replace('_', ' ')->headline();
                throw ValidationException::withMessages(['document' => "Falta documento obligatorio: {$label}."]);
            }
        }
    }

    private function attachPendingDocumentForPhase(OrderStatusService $service): void
    {
        if (! $this->documentFile) {
            return;
        }

        $this->storePhaseDocument($service);
        $this->orderStatus = $this->orderStatus->fresh(['saleOrder.customer', 'items.product', 'items.supplier', 'items.documents', 'items.events.user', 'shipmentBatches.supplier', 'shipmentBatches.items.orderStatusItem', 'documents.uploader', 'documents.generator', 'documents.item', 'purchaseOrderDocuments.purchaseOrder.supplier', 'purchaseOrderDocuments.sender', 'purchaseOrderDocuments.generator', 'purchaseOrderDocuments.approvalRequest', 'events.user']);
    }

    private function storePhaseDocument(OrderStatusService $service): void
    {
        $this->documentPhase = $this->activePhase;
        $this->documentType = $this->defaultDocumentTypeForPhase($this->activePhase);

        $this->validate([
            'documentFile' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,webp'],
            'documentNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $items = $this->documentBelongsToOrder($this->documentType)
            ? collect([null])
            : ($this->selectedGroupKey ? $this->activeGroupItems() : collect([$this->selectedItemId ? OrderStatusItem::find($this->selectedItemId) : null]));

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['item' => 'El grupo no tiene partidas disponibles para adjuntar el documento.']);
        }

        $firstDocument = null;
        foreach ($items as $item) {
            $firstDocument ??= $service->attachDocument($this->orderStatus, $item, $this->documentPhase, $this->documentType, $this->documentFile, auth()->user(), $this->documentNotes ?: null);

            if ($firstDocument && $item !== $items->first()) {
                $service->attachDocument($this->orderStatus, $item, $this->documentPhase, $this->documentType, $firstDocument->file_path, auth()->user(), $this->documentNotes ?: null);
            }
        }
        $this->reset(['documentFile', 'documentNotes']);
    }

    private function acceptedPhaseDocumentTypes(string $documentType): array
    {
        return match ($documentType) {
            'purchase_order_provider_pdf' => ['purchase_order_provider_pdf', 'purchase_order_pdf'],
            default => [$documentType],
        };
    }

    private function documentBelongsToOrder(string $documentType): bool
    {
        return in_array($documentType, ['purchase_order_provider_pdf', 'purchase_order_pdf'], true);
    }
}
