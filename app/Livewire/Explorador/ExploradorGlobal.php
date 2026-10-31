<?php

namespace App\Livewire\Explorador;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\OrderStatus;
use App\Models\OrderTracker;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderDocument;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\StockMove;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class ExploradorGlobal extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'tipo')]
    public string $type = 'all';

    #[Url(as: 'registro_tipo')]
    public ?string $selectedType = null;

    #[Url(as: 'registro')]
    public ?int $selectedId = null;

    public function updatedSearch(): void
    {
        $this->clearSelection();
    }

    public function updatedType(): void
    {
        $this->clearSelection();
    }

    public function selectResult(string $type, int $id): void
    {
        abort_unless(in_array($type, array_keys($this->availableTypes()), true), 404);

        $this->selectedType = $type;
        $this->selectedId = $id;
    }

    public function clearSelection(): void
    {
        $this->selectedType = null;
        $this->selectedId = null;
    }

    public function render()
    {
        return view('livewire.explorador.explorador-global', [
            'types' => $this->availableTypes(),
            'results' => $this->results(),
            'detail' => $this->detail(),
        ]);
    }

    private function results(): Collection
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2 && $this->type === 'all') {
            return collect();
        }

        $results = collect();
        $types = $this->type === 'all' ? array_keys($this->availableTypes()) : [$this->type];

        foreach ($types as $type) {
            $results = $results->concat($this->searchType($type, $term));
        }

        return $results->sortByDesc('date')->take(60)->values();
    }

    private function searchType(string $type, string $term): Collection
    {
        $limit = $term === '' ? 50 : 15;

        return match ($type) {
            'customer' => Entity::query()
                ->where('type', 'cliente')
                ->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('legal_name', 'like', "%{$term}%")
                    ->orWhere('rfc_ruc', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('contact_name', 'like', "%{$term}%"))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('customer', $row->id, 'Cliente', $row->rfc_ruc ?: 'CL-'.$row->id, $row->name, $row->status ?: 'Activo', $row->updated_at)),
            'quotation' => QuotationRequest::query()
                ->where(fn (Builder $query) => $query
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhere('quote_to_name', 'like', "%{$term}%")
                    ->orWhereHas('items', fn (Builder $items) => $items
                        ->where('part_number', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('quotation', $row->id, 'Solicitud', $row->reference, $row->quote_to_name, $row->status, $row->date)),
            'sale_order' => SaleOrder::query()
                ->where(fn (Builder $query) => $query
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhere('client_name', 'like', "%{$term}%"))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('sale_order', $row->id, 'Orden de venta', $row->reference, $row->client_name, $row->status, $row->date)),
            'purchase_order' => PurchaseOrder::query()->with('supplier:id,name')
                ->where(fn (Builder $query) => $query
                    ->where('reference_number', 'like', "%{$term}%")
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$term}%")))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('purchase_order', $row->id, 'Orden de compra', $row->reference_number, $row->supplier?->name, $row->status, $row->order_date)),
            'tracker' => OrderTracker::query()->with(['customer', 'supplier', 'orderStatus.saleOrder'])
                ->where(fn (Builder $query) => $query
                    ->where('operational_tracking_code', 'like', "%{$term}%")
                    ->orWhere('main_reference', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$term}%"))
                    ->orWhereHas('references', fn (Builder $references) => $references->where('reference_code', 'like', "%{$term}%")))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('tracker', $row->id, 'Pedido', $row->tracking_code ?: $row->main_reference, $row->customer?->name ?: $row->supplier?->name ?: $row->orderStatus?->saleOrder?->client_name, $row->operational_status, $row->updated_at)),
            'product' => Product::query()
                ->where(fn (Builder $query) => $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('part_number', 'like', "%{$term}%"))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('product', $row->id, 'Producto', $row->part_number ?: $row->sku, $row->name, $row->active ? 'Activo' : 'Inactivo', $row->updated_at)),
            'stock_move' => StockMove::query()->with('product:id,name,sku,part_number')
                ->where(fn (Builder $query) => $query
                    ->where('reference', 'like', "%{$term}%")
                    ->orWhereHas('product', fn (Builder $product) => $product
                        ->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%")
                        ->orWhere('part_number', 'like', "%{$term}%")))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('stock_move', $row->id, 'Movimiento Kardex', $row->reference ?: '#'.$row->id, $row->product?->name, $row->movement_type?->value ?? (string) $row->movement_type, $row->movement_date)),
            'invoice' => Invoice::query()->with('entity:id,name')
                ->where(fn (Builder $query) => $query
                    ->where('invoice_number', 'like', "%{$term}%")
                    ->orWhereHas('entity', fn (Builder $entity) => $entity->where('name', 'like', "%{$term}%")))
                ->latest()->limit($limit)->get()->map(fn ($row) => $this->resultRow('invoice', $row->id, 'Factura', $row->invoice_number, $row->entity?->name, $row->status, $row->date)),
            default => collect(),
        };
    }

    private function detail(): ?array
    {
        if (! $this->selectedType || ! $this->selectedId) {
            return null;
        }

        return match ($this->selectedType) {
            'customer' => $this->customerDetail(),
            'quotation' => $this->quotationDetail(),
            'sale_order' => $this->saleOrderDetail(),
            'purchase_order' => $this->purchaseOrderDetail(),
            'tracker' => $this->trackerDetail(),
            'product' => $this->productDetail(),
            'stock_move' => $this->stockMoveDetail(),
            'invoice' => $this->invoiceDetail(),
            default => null,
        };
    }

    private function customerDetail(): ?array
    {
        $customer = Entity::query()
            ->where('type', 'cliente')
            ->with(['machines', 'accountReceivables'])
            ->find($this->selectedId);

        if (! $customer) {
            return null;
        }

        $saleOrders = SaleOrder::with([
            'sourceQuotationRequest',
            'orderStatus.tracker',
            'purchaseOrders',
            'invoices',
        ])->where('customer_id', $customer->id)->latest()->get();

        $trackers = OrderTracker::with(['orderStatus.saleOrder'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->get()
            ->concat($saleOrders->pluck('orderStatus.tracker')->filter())
            ->unique('id')
            ->values();

        $emails = collect([$customer->email, $customer->contact_info])
            ->filter(fn ($value) => filter_var($value, FILTER_VALIDATE_EMAIL))
            ->values();

        $quotations = QuotationRequest::query()
            ->where(function (Builder $query) use ($customer, $emails): void {
                $query->where('quote_to_name', $customer->name);
                if ($emails->isNotEmpty()) {
                    $query->orWhereIn('quote_to_email', $emails)
                        ->orWhereIn('quote_to_email_alt', $emails);
                }
            })
            ->latest()
            ->get()
            ->concat($saleOrders->pluck('sourceQuotationRequest')->filter())
            ->unique('id')
            ->values();

        $invoices = Invoice::query()
            ->where('entity_id', $customer->id)
            ->when($saleOrders->isNotEmpty(), fn (Builder $query) => $query->orWhereIn('sales_order_id', $saleOrders->pluck('id')))
            ->latest()
            ->get()
            ->concat($saleOrders->flatMap->invoices)
            ->unique('id')
            ->values();

        $cases = $trackers->map(fn (OrderTracker $tracker) => [
            'type' => 'tracker',
            'id' => $tracker->id,
            'reference' => $tracker->tracking_code ?: $tracker->main_reference ?: 'Pedido #'.$tracker->id,
            'status' => $this->statusLabel($tracker->operational_status ?: $tracker->orderStatus?->saleOrder?->status),
            'date' => $this->formatDate($tracker->updated_at),
            'description' => $this->phaseLabel($tracker->operational_phase),
        ])->concat(
            $saleOrders->filter(fn (SaleOrder $order) => ! $order->orderStatus?->tracker)->map(fn (SaleOrder $order) => [
                'type' => 'sale_order',
                'id' => $order->id,
                'reference' => $order->reference,
                'status' => $this->statusLabel($order->status),
                'date' => $this->formatDate($order->date),
                'description' => 'Orden sin seguimiento iniciado',
            ])
        )->values()->all();

        $related = collect()
            ->concat($quotations->map(fn (QuotationRequest $quotation) => [
                'type' => 'quotation', 'id' => $quotation->id, 'label' => 'Solicitud',
                'reference' => $quotation->reference, 'description' => $quotation->quote_to_name, 'status' => $quotation->status,
            ]))
            ->concat($saleOrders->map(fn (SaleOrder $order) => [
                'type' => 'sale_order', 'id' => $order->id, 'label' => 'Orden de venta',
                'reference' => $order->reference, 'description' => $order->client_name, 'status' => $order->status,
            ]))
            ->concat($invoices->map(fn (Invoice $invoice) => [
                'type' => 'invoice', 'id' => $invoice->id, 'label' => 'Factura',
                'reference' => $invoice->invoice_number, 'description' => $invoice->description, 'status' => $invoice->status,
            ]))
            ->values()->all();

        $pendingBalance = $customer->accountReceivables->sum(fn ($receivable) => max(0, (float) $receivable->total_amount - (float) $receivable->paid_amount));

        return array_merge($this->detailRow('Perfil de cliente', $customer->rfc_ruc ?: 'CL-'.$customer->id, $customer->name, $customer->status ?: 'Activo', [
            ['label' => 'Expedientes', 'value' => count($cases)],
            ['label' => 'Solicitudes', 'value' => $quotations->count()],
            ['label' => 'Órdenes de venta', 'value' => $saleOrders->count()],
            ['label' => 'Facturas', 'value' => $invoices->count()],
        ], $related), [
            'date' => $this->formatDate($customer->created_at),
            'moduleUrl' => auth()->user()?->can('crm.clients.manage')
                ? route('clients.profile', $customer->id)
                : null,
            'moduleLabel' => 'Abrir ficha en CRM',
            'profile' => [
                ['label' => 'Razón social', 'value' => $customer->legal_name ?: $customer->name],
                ['label' => 'RUC', 'value' => $customer->rfc_ruc],
                ['label' => 'Contacto', 'value' => $customer->contact_name],
                ['label' => 'Cargo', 'value' => $customer->contact_position],
                ['label' => 'Correo', 'value' => $customer->email ?: $customer->contact_info],
                ['label' => 'Teléfono', 'value' => $customer->phone_mobile ?: $customer->phone_home],
                ['label' => 'Dirección', 'value' => $customer->exact_address ?: $customer->address],
                ['label' => 'Ubicación', 'value' => collect([$customer->municipality, $customer->department, $customer->country])->filter()->join(', ')],
                ['label' => 'Sitio web', 'value' => $customer->website],
                ['label' => 'Equipos registrados', 'value' => $customer->machines->count()],
                ['label' => 'Saldo pendiente', 'value' => 'NIO '.number_format($pendingBalance, 2)],
            ],
            'cases' => $cases,
        ]);
    }

    private function quotationDetail(): ?array
    {
        $row = QuotationRequest::with([
            'saleOrder.orderStatus.documents.uploader',
            'saleOrder.orderStatus.documents.generator',
            'saleOrder.orderStatus.tracker.activities.user',
            'saleOrder.orderStatus.tracker.attachments.uploader',
            'saleOrder.orderStatus.tracker.comments.user',
            'purchaseOrders.supplier',
            'items.product',
            'supplierRfqDocuments.supplier',
            'supplierBids',
        ])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        return array_merge($this->detailRow('Solicitud', $row->reference, $row->quote_to_name, $row->status, [
            ['label' => 'Partidas', 'value' => $row->items->count()],
            ['label' => 'Orden de venta', 'value' => $row->saleOrder?->reference ?: 'No generada'],
            ['label' => 'Órdenes de compra', 'value' => $row->purchaseOrders->count()],
            ['label' => 'Seguimiento', 'value' => $row->saleOrder?->orderStatus?->tracker?->tracking_code ?: 'No iniciado'],
        ], $row->purchaseOrders->map(fn ($order) => ['label' => 'Orden de compra', 'reference' => $order->reference_number, 'description' => $order->supplier?->name, 'status' => $order->status])->all()), [
            'date' => $this->formatDate($row->date),
            'currency' => $row->currency ?: 'USD',
            'amount' => (float) $row->total_amount,
            'notes' => $row->notes,
            'steps' => $this->quotationSteps($row->commercial_phase),
            'items' => $row->items->map(fn ($item) => $this->itemRow($item->part_number, $item->part_name ?: $item->description, $item->quantity, $item->unit_price, $item->line_total, $row->currency, $item->status))->all(),
            'activities' => $this->trackerActivities($row->saleOrder?->orderStatus?->tracker),
            'documents' => $this->workflowDocuments($row, $row->saleOrder, $row->purchaseOrders, $row->saleOrder?->orderStatus?->tracker),
            'comments' => $this->workflowComments($row->saleOrder?->orderStatus?->tracker, $row->purchaseOrders),
            'case' => $this->caseRow($row->saleOrder?->orderStatus?->tracker),
        ]);
    }

    private function saleOrderDetail(): ?array
    {
        $row = SaleOrder::with([
            'sourceQuotationRequest.supplierRfqDocuments.supplier',
            'purchaseOrders.supplier',
            'orderStatus.tracker.activities.user',
            'orderStatus.tracker.attachments.uploader',
            'orderStatus.tracker.comments.user',
            'orderStatus.documents.uploader',
            'orderStatus.documents.generator',
            'items.product',
        ])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        $related = collect();
        if ($row->sourceQuotationRequest) {
            $related->push(['label' => 'Solicitud', 'reference' => $row->sourceQuotationRequest->reference, 'description' => $row->sourceQuotationRequest->quote_to_name, 'status' => $row->sourceQuotationRequest->status]);
        }
        $related = $related->concat($row->purchaseOrders->map(fn ($order) => ['label' => 'Orden de compra', 'reference' => $order->reference_number, 'description' => $order->supplier?->name, 'status' => $order->status]));

        return array_merge($this->detailRow('Orden de venta', $row->reference, $row->client_name, $row->status, [
            ['label' => 'Partidas', 'value' => $row->items->count()],
            ['label' => 'Moneda', 'value' => $row->currency ?: 'No indicada'],
            ['label' => 'Total', 'value' => $row->currency.' '.number_format((float) $row->total_amount, 2)],
            ['label' => 'Seguimiento', 'value' => $row->orderStatus?->tracker?->tracking_code ?: 'No iniciado'],
        ], $related->values()->all()), [
            'date' => $this->formatDate($row->date),
            'currency' => $row->currency ?: 'USD',
            'amount' => (float) $row->total_amount,
            'notes' => $row->notes,
            'steps' => $this->saleOrderSteps($row->status),
            'items' => $row->items->map(fn ($item) => $this->itemRow($item->part_number, $item->description, $item->quantity, $item->unit_price, $item->total_price, $row->currency, $item->item_status))->all(),
            'activities' => $this->trackerActivities($row->orderStatus?->tracker),
            'documents' => $this->workflowDocuments($row->sourceQuotationRequest, $row, $row->purchaseOrders, $row->orderStatus?->tracker),
            'comments' => $this->workflowComments($row->orderStatus?->tracker, $row->purchaseOrders),
            'case' => $this->caseRow($row->orderStatus?->tracker),
        ]);
    }

    private function purchaseOrderDetail(): ?array
    {
        $row = PurchaseOrder::with([
            'supplier',
            'quotationRequest.supplierRfqDocuments.supplier',
            'saleOrder.orderStatus.tracker.activities.user',
            'saleOrder.orderStatus.tracker.attachments.uploader',
            'saleOrder.orderStatus.tracker.comments.user',
            'saleOrder.orderStatus.documents.uploader',
            'saleOrder.orderStatus.documents.generator',
            'items.product',
            'receipts',
        ])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        return array_merge($this->detailRow('Orden de compra', $row->reference_number, $row->supplier?->name, $row->status, [
            ['label' => 'Partidas', 'value' => $row->items->count()],
            ['label' => 'Moneda', 'value' => $row->currency ?: 'No indicada'],
            ['label' => 'Recepciones', 'value' => $row->receipts->count()],
            ['label' => 'Fecha esperada', 'value' => $this->formatDate($row->expected_date) ?: 'No indicada'],
        ], array_values(array_filter([
            $row->quotationRequest ? ['label' => 'Solicitud', 'reference' => $row->quotationRequest->reference, 'description' => $row->quotationRequest->quote_to_name, 'status' => $row->quotationRequest->status] : null,
            $row->saleOrder ? ['label' => 'Orden de venta', 'reference' => $row->saleOrder->reference, 'description' => $row->saleOrder->client_name, 'status' => $row->saleOrder->status] : null,
        ]))), [
            'date' => $this->formatDate($row->order_date),
            'currency' => $row->currency ?: 'USD',
            'amount' => (float) $row->total_fob,
            'notes' => $row->notes,
            'steps' => $this->purchaseOrderSteps($row->status),
            'items' => $row->items->map(fn ($item) => $this->itemRow($item->part_number, $item->part_name ?: $item->product?->name, $item->quantity, $item->unit_price, $item->total_price, $item->currency ?: $row->currency, $item->status))->all(),
            'activities' => $this->trackerActivities($row->saleOrder?->orderStatus?->tracker),
            'documents' => $this->workflowDocuments($row->quotationRequest, $row->saleOrder, collect([$row]), $row->saleOrder?->orderStatus?->tracker),
            'comments' => $this->workflowComments($row->saleOrder?->orderStatus?->tracker, collect([$row])),
            'case' => $this->caseRow($row->saleOrder?->orderStatus?->tracker),
        ]);
    }

    private function trackerDetail(): ?array
    {
        $row = OrderTracker::with([
            'customer',
            'supplier',
            'references',
            'activities.user',
            'attachments.uploader',
            'comments.user',
            'orderStatus.saleOrder.sourceQuotationRequest.supplierRfqDocuments.supplier',
            'orderStatus.saleOrder.purchaseOrders.supplier',
            'orderStatus.saleOrder.items.product',
            'orderStatus.documents.uploader',
            'orderStatus.documents.generator',
        ])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        $saleOrder = $row->orderStatus?->saleOrder;
        $purchaseOrders = $saleOrder?->purchaseOrders ?? collect();
        $documents = $this->workflowDocuments($saleOrder?->sourceQuotationRequest, $saleOrder, $purchaseOrders, $row);
        $comments = $this->workflowComments($row, $purchaseOrders);
        $resolvedPhase = $this->resolvedTrackerPhase($row, $saleOrder);
        $resolvedStatus = $this->resolvedTrackerStatus($row, $saleOrder);
        $case = $this->caseRow($row, true, $saleOrder?->client_name);
        $case['status'] = $this->statusLabel($resolvedStatus);

        return array_merge($this->detailRow('Pedido', $row->tracking_code ?: $row->main_reference, $row->customer?->name ?: $row->supplier?->name ?: $saleOrder?->client_name, $resolvedStatus, [
            ['label' => 'Fase actual', 'value' => $this->phaseLabel($resolvedPhase)],
            ['label' => 'Referencias', 'value' => $row->references->count()],
            ['label' => 'Actividades', 'value' => $row->activities->count()],
            ['label' => 'Documentos', 'value' => count($documents)],
        ], $row->references->map(fn ($reference) => ['label' => $this->referenceTypeLabel($reference->reference_type), 'reference' => $reference->reference_code, 'description' => null, 'status' => null])->all()), [
            'date' => $this->formatDate($row->created_at),
            'notes' => null,
            'currency' => $saleOrder?->currency,
            'amount' => $saleOrder ? (float) $saleOrder->total_amount : null,
            'steps' => $this->trackerSteps($resolvedPhase),
            'items' => $saleOrder?->items?->map(fn ($item) => $this->itemRow($item->part_number, $item->description, $item->quantity, $item->unit_price, $item->total_price, $saleOrder->currency, $item->item_status))->all() ?? [],
            'activities' => $this->trackerActivities($row),
            'documents' => $documents,
            'comments' => $comments,
            'case' => $case,
        ]);
    }

    private function productDetail(): ?array
    {
        $row = Product::with(['stockMoves' => fn ($query) => $query->latest()->limit(10)])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        return $this->detailRow('Producto', $row->part_number ?: $row->sku, $row->name, $row->active ? 'Activo' : 'Inactivo', [
            ['label' => 'Existencia', 'value' => $row->stock_on_hand],
            ['label' => 'Costo', 'value' => number_format((float) $row->cost, 2)],
            ['label' => 'Precio de venta', 'value' => number_format((float) $row->sales_price, 2)],
            ['label' => 'Movimientos recientes', 'value' => $row->stockMoves->count()],
        ], $row->stockMoves->map(fn ($move) => ['label' => 'Kardex', 'reference' => $move->reference ?: '#'.$move->id, 'description' => $this->formatDate($move->movement_date), 'status' => $move->movement_type?->value ?? (string) $move->movement_type])->all());
    }

    private function stockMoveDetail(): ?array
    {
        $row = StockMove::with([
            'product',
            'purchaseOrder.quotationRequest',
            'purchaseOrder.saleOrder.orderStatus.tracker',
            'saleOrder.sourceQuotationRequest',
            'saleOrder.purchaseOrders',
            'saleOrder.orderStatus.tracker',
            'user',
            'attachments.uploader',
        ])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        return array_merge($this->detailRow('Movimiento Kardex', $row->reference ?: '#'.$row->id, $row->product?->name, $row->movement_type?->value ?? (string) $row->movement_type, [
            ['label' => 'Fecha', 'value' => $this->formatDate($row->movement_date)],
            ['label' => 'Cantidad', 'value' => $row->quantity],
            ['label' => 'Saldo posterior', 'value' => $row->balance_after],
            ['label' => 'Registrado por', 'value' => $row->user?->name ?: 'Sistema'],
        ], array_values(array_filter([
            $row->purchaseOrder ? ['label' => 'Orden de compra', 'reference' => $row->purchaseOrder->reference_number, 'description' => null, 'status' => $row->purchaseOrder->status] : null,
            $row->saleOrder ? ['label' => 'Orden de venta', 'reference' => $row->saleOrder->reference, 'description' => $row->saleOrder->client_name, 'status' => $row->saleOrder->status] : null,
        ]))), [
            'documents' => $this->workflowDocuments(
                $row->saleOrder?->sourceQuotationRequest ?: $row->purchaseOrder?->quotationRequest,
                $row->saleOrder ?: $row->purchaseOrder?->saleOrder,
                $row->saleOrder?->purchaseOrders ?? collect([$row->purchaseOrder])->filter(),
                $row->saleOrder?->orderStatus?->tracker ?: $row->purchaseOrder?->saleOrder?->orderStatus?->tracker
            ),
            'case' => $this->caseRow($row->saleOrder?->orderStatus?->tracker ?: $row->purchaseOrder?->saleOrder?->orderStatus?->tracker),
        ]);
    }

    private function invoiceDetail(): ?array
    {
        $row = Invoice::with(['entity', 'uploader'])->find($this->selectedId);
        if (! $row) {
            return null;
        }

        $saleOrder = $row->sales_order_id ? SaleOrder::with(['sourceQuotationRequest', 'purchaseOrders', 'orderStatus.tracker'])->find($row->sales_order_id) : null;
        $saleOrder ??= $row->purchase_order_id ? PurchaseOrder::with(['saleOrder.sourceQuotationRequest', 'saleOrder.purchaseOrders', 'saleOrder.orderStatus.tracker'])->find($row->purchase_order_id)?->saleOrder : null;
        $saleOrder ??= $row->order_status_id ? OrderStatus::with(['saleOrder.sourceQuotationRequest', 'saleOrder.purchaseOrders', 'tracker'])->find($row->order_status_id)?->saleOrder : null;
        $saleOrder ??= filled($row->cotizacion_ref)
            ? QuotationRequest::with(['saleOrder.sourceQuotationRequest', 'saleOrder.purchaseOrders', 'saleOrder.orderStatus.tracker'])
                ->where('reference', $row->cotizacion_ref)
                ->first()?->saleOrder
            : null;

        $related = $saleOrder ? array_values(array_filter([
            $saleOrder->sourceQuotationRequest ? ['label' => 'Solicitud', 'reference' => $saleOrder->sourceQuotationRequest->reference, 'description' => $saleOrder->sourceQuotationRequest->quote_to_name, 'status' => $saleOrder->sourceQuotationRequest->status] : null,
            ['label' => 'Orden de venta', 'reference' => $saleOrder->reference, 'description' => $saleOrder->client_name, 'status' => $saleOrder->status],
            $saleOrder->orderStatus?->tracker ? ['label' => 'Pedido', 'reference' => $saleOrder->orderStatus->tracker->tracking_code ?: $saleOrder->orderStatus->tracker->main_reference, 'description' => $saleOrder->client_name, 'status' => $saleOrder->orderStatus->tracker->operational_status] : null,
        ])) : [];

        return array_merge($this->detailRow('Factura', $row->invoice_number, $row->entity?->name, $row->status, [
            ['label' => 'Fecha', 'value' => $this->formatDate($row->date)],
            ['label' => 'Vencimiento', 'value' => $this->formatDate($row->due_date) ?: 'No indicado'],
            ['label' => 'Moneda', 'value' => $row->currency ?: 'No indicada'],
            ['label' => 'Total', 'value' => ($row->currency ?: '').' '.number_format((float) $row->total, 2)],
        ], $related), [
            'documents' => $saleOrder
                ? $this->workflowDocuments($saleOrder->sourceQuotationRequest, $saleOrder, $saleOrder->purchaseOrders, $saleOrder->orderStatus?->tracker)
                : ($row->invoice_file_path ? [$this->documentRow($row->invoice_number.'.pdf', 'Factura', $row->date, route('finance.documents.download', ['type' => 'invoice', 'id' => $row->id]), 'application/pdf', $row->uploader?->name, null, 'Finanzas')] : []),
            'comments' => $this->workflowComments($saleOrder?->orderStatus?->tracker, $saleOrder?->purchaseOrders ?? collect()),
            'case' => $this->caseRow($saleOrder?->orderStatus?->tracker),
        ]);
    }

    private function availableTypes(): array
    {
        $user = auth()->user();
        $types = [];

        if ($user?->hasAnyPermission(['customer_request.view', 'order.view', 'direct_order.view', 'tracker.view']) || $user?->canAccess('finance')) {
            $types['customer'] = 'Clientes';
        }

        if ($user?->hasAnyPermission(['customer_request.view', 'supplier_bid.view', 'client_offer.view', 'order_approval.view'])) {
            $types['quotation'] = 'Solicitudes';
        }
        if ($user?->hasAnyPermission(['order.view', 'direct_order.view', 'tracker.view'])) {
            $types['sale_order'] = 'Órdenes de venta';
        }
        if ($user?->canAccess('purchases')) {
            $types['purchase_order'] = 'Órdenes de compra';
        }
        if ($user?->can('tracker.view')) {
            $types['tracker'] = 'Pedidos y seguimiento';
        }
        if ($user?->canAccess('inventory')) {
            $types['product'] = 'Productos';
            $types['stock_move'] = 'Kardex';
        }
        if ($user?->canAccess('finance')) {
            $types['invoice'] = 'Facturas';
        }

        return $types;
    }

    private function resultRow(string $type, int $id, string $category, ?string $reference, ?string $title, ?string $status, mixed $date): array
    {
        $status = $this->statusLabel($status);
        $dateLabel = $this->formatDate($date);

        return compact('type', 'id', 'category', 'reference', 'title', 'status', 'date', 'dateLabel');
    }

    private function detailRow(string $category, ?string $reference, ?string $title, ?string $status, array $facts, array $related): array
    {
        $status = $this->statusLabel($status);
        $related = collect($related)->map(function (array $record): array {
            $record['label'] = $this->referenceTypeLabel($record['label'] ?? null);
            $record['status'] = $this->statusLabel($record['status'] ?? null);

            return $record;
        })->all();

        return array_merge(compact('category', 'reference', 'title', 'status', 'facts', 'related'), [
            'date' => null,
            'currency' => null,
            'amount' => null,
            'notes' => null,
            'steps' => [],
            'items' => [],
            'activities' => [],
            'documents' => [],
            'comments' => [],
            'case' => null,
            'profile' => [],
            'cases' => [],
            'moduleUrl' => null,
            'moduleLabel' => null,
        ]);
    }

    private function itemRow(?string $partNumber, ?string $description, mixed $quantity, mixed $unitPrice, mixed $total, ?string $currency, ?string $status): array
    {
        return compact('partNumber', 'description', 'quantity', 'unitPrice', 'total', 'currency', 'status');
    }

    private function caseRow(?OrderTracker $tracker, bool $isCurrent = false, ?string $fallbackCustomer = null): ?array
    {
        if (! $tracker) {
            return null;
        }

        $tracker->loadMissing(['customer', 'supplier']);

        return [
            'id' => $tracker->id,
            'reference' => $tracker->tracking_code ?: $tracker->main_reference ?: 'Pedido #'.$tracker->id,
            'customer' => $tracker->customer?->name ?: $tracker->supplier?->name ?: $fallbackCustomer ?: 'Cliente no indicado',
            'status' => $this->statusLabel($tracker->operational_status),
            'isCurrent' => $isCurrent,
        ];
    }

    private function workflowDocuments(?QuotationRequest $quotation, ?SaleOrder $saleOrder, Collection $purchaseOrders, ?OrderTracker $tracker): array
    {
        $documents = collect();

        if ($quotation?->evidence_attachment_path) {
            $documents->push($this->documentRow(
                basename($quotation->evidence_attachment_path),
                'Adjunto de solicitud',
                $quotation->created_at,
                route('private.customer-request-evidence', $quotation),
                null,
                $quotation->user?->name,
                $quotation->notes,
                'Solicitud'
            ));
        }

        if ($quotation) {
            $quotation->loadMissing(['supplierRfqDocuments.supplier', 'user']);

            foreach ($quotation->supplierRfqDocuments->whereNotNull('pdf_path') as $rfq) {
                $documents->push($this->documentRow(
                    ($rfq->rfq_number ?: 'RFQ-'.$rfq->id).'.pdf',
                    'RFQ proveedor',
                    $rfq->generated_at ?: $rfq->created_at,
                    route('sales.rfq-documents.pdf', $rfq),
                    'application/pdf',
                    $rfq->generator?->name,
                    $rfq->supplier?->name,
                    'Licitación'
                ));
            }

            if ($quotation->client_offer_pdf_path) {
                $documents->push($this->documentRow(
                    'Oferta_'.$quotation->reference.'.pdf',
                    'Oferta final al cliente',
                    $quotation->updated_at,
                    route('sales.offers.download-final-pdf', $quotation),
                    'application/pdf',
                    null,
                    null,
                    'Oferta al cliente'
                ));
            }
        }

        if ($saleOrder) {
            $documents->push($this->documentRow(
                'Orden_'.$saleOrder->reference.'.pdf',
                'Orden de venta',
                $saleOrder->generated_at ?: $saleOrder->created_at,
                route('sales.orders.pdf', $saleOrder),
                'application/pdf',
                $saleOrder->generator?->name,
                null,
                'Orden de venta'
            ));

            $saleOrder->loadMissing(['generator', 'orderStatus.documents.uploader', 'orderStatus.documents.generator']);
            foreach ($saleOrder->orderStatus?->documents ?? collect() as $document) {
                $documents->push($this->documentRow(
                    $document->file_name,
                    $document->document_type ?: 'Documento operativo',
                    $document->created_at,
                    route('order-status-documents.download', $document),
                    null,
                    $document->uploader?->name ?: $document->generator?->name,
                    $document->notes,
                    $document->phase
                ));
            }
        }

        $purchaseOrderIds = $purchaseOrders->pluck('id')->filter()->values();
        if ($purchaseOrderIds->isNotEmpty()) {
            PurchaseOrderDocument::with(['purchaseOrder.supplier', 'generator'])
                ->whereIn('purchase_order_id', $purchaseOrderIds)
                ->whereNotNull('pdf_path')
                ->get()
                ->each(function (PurchaseOrderDocument $document) use ($documents): void {
                    $documents->push($this->documentRow(
                        'OC_'.$document->purchaseOrder?->reference_number.'.pdf',
                        'Orden de compra a proveedor',
                        $document->generated_at ?: $document->created_at,
                        route('purchase-order-documents.pdf', $document),
                        'application/pdf',
                        $document->generator?->name,
                        $document->purchaseOrder?->supplier?->name,
                        'Compras'
                    ));
                });

            StockMove::with(['attachments.uploader'])
                ->whereIn('purchase_order_id', $purchaseOrderIds)
                ->when($saleOrder, fn (Builder $query) => $query->orWhere('sale_order_id', $saleOrder->id))
                ->get()
                ->flatMap->attachments
                ->each(function ($attachment) use ($documents): void {
                    $documents->push($this->documentRow(
                        $attachment->file_name,
                        'Evidencia Kardex',
                        $attachment->created_at,
                        route('private.stock-move-attachment-file', $attachment),
                        $attachment->mime_type,
                        $attachment->uploader?->name,
                        null,
                        'Kardex'
                    ));
                });
        }

        if ($tracker) {
            $tracker->loadMissing(['attachments.uploader']);
            foreach ($tracker->attachments as $attachment) {
                $documents->push($this->documentRow(
                    $attachment->file_name,
                    $attachment->file_type ?: 'Adjunto',
                    $attachment->created_at,
                    route('tracker.attachments.show', $attachment),
                    $attachment->file_type,
                    $attachment->uploader?->name,
                    $attachment->comment,
                    $attachment->phase
                ));
            }
        }

        $orderStatusId = $saleOrder?->orderStatus?->id;
        if ($saleOrder || $purchaseOrderIds->isNotEmpty() || $orderStatusId) {
            $invoices = Invoice::with('uploader')
                ->where(function (Builder $query) use ($saleOrder, $purchaseOrderIds, $orderStatusId): void {
                    if ($saleOrder) {
                        $query->orWhere('sales_order_id', $saleOrder->id);
                    }
                    if ($purchaseOrderIds->isNotEmpty()) {
                        $query->orWhereIn('purchase_order_id', $purchaseOrderIds);
                    }
                    if ($orderStatusId) {
                        $query->orWhere('order_status_id', $orderStatusId);
                    }
                })->get();

            foreach ($invoices->whereNotNull('invoice_file_path') as $invoice) {
                $documents->push($this->documentRow(
                    $invoice->invoice_number ?: 'Factura #'.$invoice->id,
                    'Factura',
                    $invoice->date ?: $invoice->created_at,
                    route('finance.documents.download', ['type' => 'invoice', 'id' => $invoice->id]),
                    null,
                    $invoice->uploader?->name,
                    $invoice->description,
                    'Finanzas'
                ));
            }

            $receivables = ($saleOrder || $orderStatusId)
                ? AccountReceivable::with('creator')
                    ->where(function (Builder $query) use ($saleOrder, $orderStatusId): void {
                        if ($saleOrder) {
                            $query->orWhere('sales_order_id', $saleOrder->id);
                        }
                        if ($orderStatusId) {
                            $query->orWhere('order_status_id', $orderStatusId);
                        }
                    })->get()
                : collect();

            $payables = ($purchaseOrderIds->isNotEmpty() || $orderStatusId)
                ? AccountPayable::with('creator')
                    ->where(function (Builder $query) use ($purchaseOrderIds, $orderStatusId): void {
                        if ($purchaseOrderIds->isNotEmpty()) {
                            $query->orWhereIn('purchase_order_id', $purchaseOrderIds);
                        }
                        if ($orderStatusId) {
                            $query->orWhere('order_status_id', $orderStatusId);
                        }
                    })->get()
                : collect();

            foreach ($receivables->whereNotNull('support_document_path') as $receivable) {
                $documents->push($this->documentRow(
                    $receivable->receivable_code ?: 'CXC #'.$receivable->id,
                    'Soporte de cuenta por cobrar',
                    $receivable->created_at,
                    route('finance.documents.download', ['type' => 'cxc', 'id' => $receivable->id]),
                    null,
                    $receivable->creator?->name,
                    null,
                    'Finanzas'
                ));
            }

            foreach ($payables->whereNotNull('support_document_path') as $payable) {
                $documents->push($this->documentRow(
                    $payable->payable_code ?: 'CXP #'.$payable->id,
                    'Soporte de cuenta por pagar',
                    $payable->created_at,
                    route('finance.documents.download', ['type' => 'cxp', 'id' => $payable->id]),
                    null,
                    $payable->creator?->name,
                    null,
                    'Finanzas'
                ));
            }

            if ($receivables->isNotEmpty() || $payables->isNotEmpty() || $invoices->isNotEmpty()) {
                Payment::with('registrar')
                    ->where(function (Builder $query) use ($receivables, $payables, $invoices): void {
                        if ($receivables->isNotEmpty()) {
                            $query->orWhereIn('accounts_receivable_id', $receivables->pluck('id'));
                        }
                        if ($payables->isNotEmpty()) {
                            $query->orWhereIn('accounts_payable_id', $payables->pluck('id'));
                        }
                        if ($invoices->isNotEmpty()) {
                            $query->orWhereIn('invoice_id', $invoices->pluck('id'));
                        }
                    })
                    ->whereNotNull('payment_evidence_path')
                    ->get()
                    ->each(function (Payment $payment) use ($documents): void {
                        $documents->push($this->documentRow(
                            $payment->payment_code ?: $payment->payment_reference ?: 'Pago #'.$payment->id,
                            'Evidencia de pago',
                            $payment->payment_date ?: $payment->created_at,
                            route('finance.documents.download', ['type' => 'payment', 'id' => $payment->id]),
                            null,
                            $payment->registrar?->name,
                            $payment->notes,
                            'Finanzas'
                        ));
                    });
            }
        }

        return $documents
            ->filter(fn (array $document) => filled($document['viewUrl']))
            ->unique(fn (array $document) => $document['viewUrl'].'|'.$document['name'])
            ->sortByDesc('timestamp')
            ->values()
            ->all();
    }

    private function workflowComments(?OrderTracker $tracker, Collection $purchaseOrders): array
    {
        $comments = collect();

        if ($tracker) {
            $tracker->loadMissing(['comments.user', 'attachments.uploader']);
            foreach ($tracker->comments as $comment) {
                $comments->push([
                    'text' => $comment->comment,
                    'author' => $comment->user?->name ?: 'Sistema',
                    'context' => $comment->visibility === 'internal' ? 'Comentario interno' : 'Seguimiento',
                    'date' => $this->formatDate($comment->created_at, true),
                    'timestamp' => optional($comment->created_at)->timestamp,
                ]);
            }

            foreach ($tracker->attachments->whereNotNull('comment') as $attachment) {
                $comments->push([
                    'text' => $attachment->comment,
                    'author' => $attachment->uploader?->name ?: 'Sistema',
                    'context' => 'Comentario de adjunto · '.$this->phaseLabel($attachment->phase),
                    'date' => $this->formatDate($attachment->created_at, true),
                    'timestamp' => optional($attachment->created_at)->timestamp,
                ]);
            }
        }

        $purchaseOrderIds = $purchaseOrders->pluck('id')->filter();
        if ($purchaseOrderIds->isNotEmpty()) {
            PurchaseOrderDocument::with('generator')
                ->whereIn('purchase_order_id', $purchaseOrderIds)
                ->get()
                ->each(function (PurchaseOrderDocument $document) use ($comments): void {
                    foreach (['internal_comments' => 'Comentario interno de compra', 'supplier_comments' => 'Comentario para proveedor', 'special_instructions' => 'Instrucción especial'] as $field => $context) {
                        if (filled($document->{$field})) {
                            $comments->push([
                                'text' => $document->{$field},
                                'author' => $document->generator?->name ?: 'Sistema',
                                'context' => $context,
                                'date' => $this->formatDate($document->updated_at, true),
                                'timestamp' => optional($document->updated_at)->timestamp,
                            ]);
                        }
                    }
                });
        }

        return $comments->sortByDesc('timestamp')->values()->all();
    }

    private function documentRow(string $name, string $type, mixed $date, string $viewUrl, ?string $mimeType = null, ?string $user = null, ?string $notes = null, ?string $phase = null): array
    {
        $extension = strtolower(pathinfo(parse_url($viewUrl, PHP_URL_PATH) ?: $name, PATHINFO_EXTENSION));
        $isImage = str_starts_with((string) $mimeType, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        $phase = $this->phaseLabel($phase);
        $type = $this->documentTypeLabel($type, $phase);
        $name = $this->documentName($name, $type);

        return [
            'name' => $name,
            'type' => $type,
            'date' => $this->formatDate($date, true),
            'timestamp' => $this->dateTimestamp($date),
            'viewUrl' => $viewUrl,
            'downloadUrl' => $viewUrl.(str_contains($viewUrl, '?') ? '&' : '?').'download=1',
            'isImage' => $isImage,
            'user' => $user,
            'notes' => $notes,
            'phase' => $phase,
        ];
    }

    private function trackerActivities(?OrderTracker $tracker): array
    {
        if (! $tracker) {
            return [];
        }

        $tracker->loadMissing('activities.user');

        return $tracker->activities->take(20)->map(fn ($activity) => [
            'title' => $this->activityLabel($activity->action),
            'description' => $activity->description,
            'phase' => $this->phaseLabel($activity->phase),
            'user' => $activity->user?->name ?: 'Sistema',
            'date' => $this->formatDate($activity->created_at, true),
        ])->all();
    }

    private function saleOrderSteps(?string $status): array
    {
        $statuses = ['generated', 'confirmed', 'processing', 'shipped', 'completed'];
        $labels = ['Pedido recibido', 'Confirmado', 'En preparación', 'En tránsito', 'Entregado'];

        return $this->steps($statuses, $labels, $status);
    }

    private function purchaseOrderSteps(?string $status): array
    {
        return $this->steps(['draft', 'ordered', 'shipped', 'customs', 'received'], ['Borrador', 'Ordenada', 'Embarcada', 'En aduana', 'Recibida'], $status);
    }

    private function quotationSteps(?string $phase): array
    {
        return $this->steps(['customer_request', 'supplier_bid', 'client_offer', 'approval'], ['1. Solicitud del cliente', '2. Cotización con proveedores', '3. Oferta al cliente', '4. Aprobación del cliente'], $phase);
    }

    private function trackerSteps(?string $phase): array
    {
        return $this->steps(['solicitud', 'supplier_bid', 'client_offer', 'aprobacion', 'pedido_extranjero'], ['1. Solicitud del cliente', '2. Cotización con proveedores', '3. Oferta al cliente', '4. Aprobación del cliente', '5. Compra, entrega y facturación'], $phase);
    }

    private function resolvedTrackerPhase(OrderTracker $tracker, ?SaleOrder $saleOrder): string
    {
        if ($saleOrder) {
            return 'pedido_extranjero';
        }

        $quotation = $tracker->references
            ->first(fn ($reference) => in_array($reference->source_module, ['quotation_request', 'quotation_requests'], true))
            ?->source_id;

        if ($quotation) {
            $request = QuotationRequest::find($quotation);
            if ($request?->client_offer_pdf_path || in_array($request?->commercial_phase, ['client_offer', 'approval'], true)) {
                return $request->commercial_phase === 'approval' ? 'aprobacion' : 'client_offer';
            }
        }

        return $tracker->operational_phase ?: 'solicitud';
    }

    private function resolvedTrackerStatus(OrderTracker $tracker, ?SaleOrder $saleOrder): ?string
    {
        if (! $saleOrder) {
            return $tracker->operational_status;
        }

        $hasInvoice = Invoice::query()
            ->where(function (Builder $query) use ($saleOrder): void {
                $query->where('sales_order_id', $saleOrder->id);
                if ($saleOrder->orderStatus?->id) {
                    $query->orWhere('order_status_id', $saleOrder->orderStatus->id);
                }
                if ($saleOrder->sourceQuotationRequest?->reference) {
                    $query->orWhere('cotizacion_ref', $saleOrder->sourceQuotationRequest->reference);
                }
            })
            ->exists();

        if ($hasInvoice) {
            return 'Facturación registrada';
        }

        return $saleOrder->status ?: $tracker->operational_status;
    }

    private function steps(array $values, array $labels, ?string $current): array
    {
        $currentIndex = array_search($current, $values, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        return collect($labels)->map(fn (string $label, int $index) => [
            'label' => $label,
            'complete' => $index <= $currentIndex,
            'current' => $index === $currentIndex,
        ])->all();
    }

    private function statusLabel(?string $status): ?string
    {
        if (blank($status)) {
            return null;
        }

        return match (strtolower($status)) {
            'generated' => 'Generado',
            'confirmed' => 'Confirmado',
            'processing' => 'En proceso',
            'shipped' => 'En tránsito',
            'completed' => 'Completado',
            'received', 'recibida' => 'Recibido',
            'pending', 'pendiente' => 'Pendiente',
            'paid', 'pagado' => 'Pagado',
            'draft' => 'Borrador',
            'ordered' => 'Ordenado al proveedor',
            'customs' => 'En aduana',
            'final_pdf_generated' => 'Oferta final generada',
            'approved_to_send' => 'Aprobado para enviar',
            'supplier_bid' => 'Cotización con proveedores',
            'client_offer' => 'Oferta al cliente',
            'pedido_extranjero' => 'Compra y seguimiento',
            default => str($status)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    private function phaseLabel(?string $phase): string
    {
        if (blank($phase)) {
            return 'General';
        }

        return match (strtolower($phase)) {
            'solicitud', 'customer_request' => 'Solicitud del cliente',
            'supplier_bid', 'supplier_response', 'supplier_selection', 'licitacion' => 'Cotización con proveedores',
            'client_offer', 'client_offer_version', 'offer' => 'Oferta al cliente',
            'approval', 'aprobacion', 'customer_approval' => 'Aprobación del cliente',
            'purchase_order', 'purchase_preparation', 'pedido_extranjero', 'compras' => 'Compra a proveedores',
            'shipment', 'logistics', 'aduana' => 'Logística y transporte',
            'warehouse', 'bodega', 'kardex' => 'Bodega y Kardex',
            'delivery', 'dispatch', 'entrega' => 'Despacho y entrega',
            'invoice', 'finance', 'finanzas' => 'Facturación y pagos',
            'direct_order' => 'Orden de venta directa',
            default => str($phase)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    private function referenceTypeLabel(?string $type): string
    {
        if (blank($type)) {
            return 'Registro relacionado';
        }

        return match (strtolower($type)) {
            'quotation', 'quotation_request', 'solicitud' => 'Solicitud del cliente',
            'sale_order', 'foreign_order', 'orden de venta' => 'Orden de venta',
            'purchase_order', 'orden de compra' => 'Orden de compra',
            'tracker', 'pedido' => 'Pedido / expediente',
            'invoice', 'factura' => 'Factura',
            'stock_move', 'kardex' => 'Movimiento de Kardex',
            'client_offer_version' => 'Versión de oferta al cliente',
            default => str($type)->replace(['_', '-'], ' ')->headline()->toString(),
        };
    }

    private function activityLabel(?string $action): string
    {
        $normalized = str($action ?: 'actividad')->replace(['.', '-'], '_')->lower()->toString();

        return match ($normalized) {
            'client_offer_version_created' => 'Versión de oferta al cliente creada',
            'client_offer_final_pdf_generated', 'client_offer_final_pdf_created' => 'PDF final de oferta al cliente generado',
            'rfq_pdf_generated', 'supplier_bid_rfq_pdf_generated' => 'RFQ para proveedor generado',
            'order_status_created' => 'Seguimiento operativo iniciado',
            'tracker_created' => 'Expediente del pedido creado',
            'purchase_order_created' => 'Orden de compra creada',
            'invoice_created' => 'Factura registrada',
            'packing_list_generated' => 'Lista de empaque generada',
            default => str($action ?: 'Actividad')->replace(['_', '.', '-'], ' ')->headline()->toString(),
        };
    }

    private function documentTypeLabel(string $type, string $phase): string
    {
        if (str_contains(strtolower($type), 'pdf') || str_contains(strtolower($type), 'application/')) {
            return match ($phase) {
                'Solicitud del cliente' => 'Documento de solicitud',
                'Cotización con proveedores' => 'RFQ o cotización de proveedor',
                'Oferta al cliente' => 'Oferta comercial al cliente',
                'Aprobación del cliente' => 'Documento de aprobación',
                'Compra a proveedores' => 'Orden o documento de compra',
                'Bodega y Kardex' => 'Evidencia de Kardex',
                'Facturación y pagos' => 'Documento financiero',
                default => 'Documento PDF',
            };
        }

        return str($type)->replace(['_', '-'], ' ')->headline()->toString();
    }

    private function documentName(string $name, string $type): string
    {
        $normalized = strtolower($name);

        return match (true) {
            str_contains($normalized, 'packing') => 'Lista de empaque',
            str_contains($normalized, 'oferta') || str_contains($normalized, 'client_offer') => 'Oferta final al cliente',
            str_contains($normalized, 'rfq') => 'Solicitud de cotización a proveedor',
            str_contains($normalized, 'orden_') || str_contains($normalized, 'sale_order') => 'Orden de venta',
            str_contains($normalized, 'factura') || str_contains($normalized, 'invoice') => 'Factura',
            default => $type,
        };
    }

    private function formatDate(mixed $date, bool $withTime = false): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    private function dateTimestamp(mixed $date): int
    {
        if (blank($date)) {
            return 0;
        }

        try {
            return Carbon::parse($date)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }
}
