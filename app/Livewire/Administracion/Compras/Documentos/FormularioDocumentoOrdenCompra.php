<?php

namespace App\Livewire\Administracion\Compras\Documentos;

use App\Models\ApprovalRequest;
use App\Models\PurchaseOrderDocument;
use App\Services\PurchaseOrderDocumentService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** @property-read string|null $pdfUrl */
#[Layout('layouts.app')]
class FormularioDocumentoOrdenCompra extends Component
{
    public ApprovalRequest $approval;

    public PurchaseOrderDocument $document;

    public ?int $purchaseOrderId = null;

    public string $quote_to_name = '';

    public string $consignee_name = '';

    public string $consignee_comment = '';

    public string $consignee_urgent_comment = '';

    public string $consignee_address_1 = '';

    public string $consignee_address_2 = '';

    public string $consignee_country = '';

    public string $consignee_city = '';

    public string $consignee_phone = '';

    public string $consignee_emails = '';

    public string $shipping_method = '';

    public string $shipping_terms = '';

    public string $incoterm = '';

    public string $carrier_name = '';

    public ?string $estimated_shipping_date = null;

    public ?string $estimated_arrival_date = null;

    public string $shipping_comments = '';

    public string $supplier_comments = '';

    public string $internal_comments = '';

    public string $document_footer_comments = '';

    public string $special_instructions = '';

    public function mount(ApprovalRequest $approval, PurchaseOrderDocumentService $service): void
    {
        abort_unless($this->canAny(['purchase_order_provider.view', 'purchase_order_document.view']), 403);

        $this->approval = $approval->load(['tracker', 'purchaseOrders.supplier']);
        $this->purchaseOrderId = request()->integer('purchase_order') ?: $this->approval->purchaseOrders->first()?->id;
        $purchaseOrder = $this->approval->purchaseOrders->firstWhere('id', $this->purchaseOrderId);
        abort_unless($purchaseOrder, 404);
        $this->document = $service->documentForPurchaseOrder($approval, $purchaseOrder);
        $this->fillFromDocument();
    }

    public function saveDocumentData(): void
    {
        abort_unless($this->canAny(['purchase_order_provider.edit', 'purchase_order_document.edit']), 403);

        $this->validate($this->rules());
        $this->validateEmails();

        $this->document->forceFill($this->payload())->save();
        $this->document = $this->document->refresh();

        session()->flash('message', 'Datos del documento guardados.');
    }

    public function previewPdf(): void
    {
        $this->saveDocumentData();
        session()->flash('message', 'Borrador guardado. Usa Ver PDF cuando exista un documento generado.');
    }

    public function generatePdf(PurchaseOrderDocumentService $service): void
    {
        $this->generatePurchaseOrderPdf($service);
    }

    public function generatePurchaseOrderPdf(PurchaseOrderDocumentService $service): void
    {
        abort_unless($this->canAny(['purchase_order_provider.generate_pdf', 'purchase_order_document.generate_pdf', 'purchase_order.generate_pdf']), 403);

        $this->saveDocumentData();

        try {
            $service->generatePdf($this->document->refresh(), auth()->user());
            $this->document = $this->document->refresh();
            session()->flash('message', 'PDF generado y adjuntado al seguimiento.');
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.'));
        }
    }

    public function regeneratePdf(PurchaseOrderDocumentService $service): void
    {
        abort_unless($this->canAny(['purchase_order_provider.regenerate_pdf', 'purchase_order_document.regenerate_pdf']), 403);

        $this->generatePurchaseOrderPdf($service);
    }

    public function attachToOrderStatus(): void
    {
        session()->flash('message', 'El PDF se adjunta al Order Status automaticamente al generarse.');
    }

    public function attachToOrderTracker(): void
    {
        session()->flash('message', 'El PDF se adjunta al Order Tracker automaticamente al generarse.');
    }

    public function markSentToProvider(PurchaseOrderDocumentService $service): void
    {
        abort_unless($this->canAny(['purchase_order_provider.edit', 'purchase_order_document.edit', 'order_status.manage']), 403);

        try {
            $this->document = $service->markSentToProvider($this->document->refresh(), auth()->user());
            session()->flash('message', 'OC marcada como enviada al proveedor.');
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo completar la acción. Revisa los datos e intenta de nuevo.'));
        }
    }

    public function getPdfUrlProperty(): ?string
    {
        if (
            ! $this->document->pdf_path
            || ! Storage::disk('private')->exists($this->document->pdf_path)
        ) {
            return null;
        }

        return route('purchase-order-documents.pdf', $this->document);
    }

    public function render(PurchaseOrderDocumentService $service)
    {
        return view('livewire.administracion.compras.documentos.orden-compra.formulario-documento', [
            'items' => $service->approvedItems($this->approval, $this->document->purchaseOrder),
            'purchaseOrders' => $this->approval->purchaseOrders,
            'pdfUrl' => $this->pdfUrl,
        ]);
    }

    private function fillFromDocument(): void
    {
        foreach (array_keys($this->payload()) as $field) {
            $value = $this->document->{$field};
            $this->{$field} = $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : (string) ($value ?? '');
        }
    }

    private function payload(): array
    {
        return [
            'quote_to_name' => $this->quote_to_name,
            'consignee_name' => $this->consignee_name,
            'consignee_comment' => $this->consignee_comment ?: null,
            'consignee_urgent_comment' => $this->consignee_urgent_comment ?: null,
            'consignee_address_1' => $this->consignee_address_1,
            'consignee_address_2' => $this->consignee_address_2 ?: null,
            'consignee_country' => $this->consignee_country,
            'consignee_city' => $this->consignee_city,
            'consignee_phone' => $this->consignee_phone,
            'consignee_emails' => $this->consignee_emails ?: null,
            'shipping_method' => $this->shipping_method ?: null,
            'shipping_terms' => $this->shipping_terms ?: null,
            'incoterm' => $this->incoterm ?: null,
            'carrier_name' => $this->carrier_name ?: null,
            'estimated_shipping_date' => $this->estimated_shipping_date ?: null,
            'estimated_arrival_date' => $this->estimated_arrival_date ?: null,
            'shipping_comments' => $this->shipping_comments ?: null,
            'supplier_comments' => $this->supplier_comments ?: null,
            'internal_comments' => $this->internal_comments ?: null,
            'document_footer_comments' => $this->document_footer_comments ?: null,
            'special_instructions' => $this->special_instructions ?: null,
        ];
    }

    private function rules(): array
    {
        return [
            'quote_to_name' => ['required', 'string', 'max:180'],
            'consignee_name' => ['required', 'string', 'max:180'],
            'consignee_address_1' => ['required', 'string', 'max:240'],
            'consignee_address_2' => ['nullable', 'string', 'max:240'],
            'consignee_country' => ['required', 'string', 'max:120'],
            'consignee_city' => ['required', 'string', 'max:120'],
            'consignee_phone' => ['required', 'string', 'max:80'],
            'consignee_emails' => ['nullable', 'string', 'max:500'],
            'consignee_comment' => ['nullable', 'string', 'max:1500'],
            'consignee_urgent_comment' => ['nullable', 'string', 'max:1000'],
            'shipping_method' => ['nullable', 'string', 'max:120'],
            'shipping_terms' => ['nullable', 'string', 'max:180'],
            'incoterm' => ['nullable', 'string', 'max:40'],
            'carrier_name' => ['nullable', 'string', 'max:160'],
            'estimated_shipping_date' => ['nullable', 'date'],
            'estimated_arrival_date' => ['nullable', 'date'],
            'shipping_comments' => ['nullable', 'string', 'max:1500'],
            'supplier_comments' => ['nullable', 'string', 'max:1500'],
            'internal_comments' => ['nullable', 'string', 'max:1500'],
            'document_footer_comments' => ['nullable', 'string', 'max:1500'],
            'special_instructions' => ['nullable', 'string', 'max:1500'],
        ];
    }

    private function validateEmails(): void
    {
        $emails = collect(explode(',', $this->consignee_emails))
            ->map(fn ($email) => trim($email))
            ->filter();

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages(['consignee_emails' => "Correo invalido: {$email}"]);
            }
        }
    }

    private function canAny(array $permissions): bool
    {
        $user = auth()->user();

        return (bool) $user && collect($permissions)->contains(fn (string $permission) => $user->can($permission));
    }
}
