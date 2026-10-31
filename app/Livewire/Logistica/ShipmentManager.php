<?php

namespace App\Livewire\Logistica;

use App\Domain\Logistica\Events\PackageShippedByCargoTrans;
use App\Exports\ShipmentsExport;
use App\Models\AccountReceivable;
use App\Models\ActivityLog;
use App\Models\ClientMachine;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\LogisticsShipment;
use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use App\Services\OrderStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

#[Layout('layouts.app')]
class ShipmentManager extends Component
{
    public ?int $selectedOrderStatusId = null;

    public ?array $selected_order = null;

    public array $order_items = [];

    public array $dispatch = [];

    public string $shipping_cost = '';

    public string $currency = 'USD';

    public string $client_id = '';

    public string $client_machine_id = '';

    public string $notes = '';

    public bool $generate_invoice = false;

    public string $invoice_number = '';

    public string $invoice_condition = 'Contado';

    public string $invoice_due_date = '';

    public function updatedSelectedOrderStatusId($id): void
    {
        $this->reset(['selected_order', 'order_items', 'dispatch', 'client_id', 'client_machine_id', 'notes', 'generate_invoice', 'invoice_number']);
        $this->invoice_condition = 'Contado';
        $this->invoice_due_date = now()->addDays(30)->toDateString();

        if (! $id) {
            return;
        }

        $status = $this->dispatchableOrderStatuses()->firstWhere('id', (int) $id);

        if (! $status) {
            return;
        }

        $this->selected_order = [
            'id' => $status->id,
            'reference' => $status->order_code,
            'tracking_code' => $status->tracker?->tracking_code,
            'client_name' => $status->saleOrder?->client_name ?: $status->customer?->name,
            'status' => $status->operational_status,
            'ship_together' => $this->canShipTogether($status),
        ];

        $this->client_id = (string) ($status->customer_id ?: $status->saleOrder?->customer_id ?: '');

        foreach ($status->items as $item) {
            $pending = $this->pendingDispatchQuantity($item);
            if ($pending <= 0) {
                continue;
            }

            $allowed = $this->canDispatchItem($status, $item);

            $this->order_items[] = [
                'id' => $item->id,
                'part_number' => $item->part_number ?: $item->product?->part_number ?: $item->product?->sku,
                'description' => $item->part_name ?: $item->product?->name ?: 'Producto',
                'brand' => $item->brand ?: $item->product?->brand,
                'model' => $item->model,
                'quantity_ordered' => (int) ceil((float) $item->quantity_ordered),
                'quantity_kardex_output' => (int) ceil((float) $item->quantity_kardex_output),
                'quantity_dispatched' => (int) ceil((float) $item->quantity_dispatched),
                'quantity_pending' => $pending,
                'allowed' => $allowed,
                'decision_label' => $this->decisionLabel($status, $item, $allowed),
            ];

            $this->dispatch[$item->id] = [
                'selected' => $allowed,
                'qty' => $pending,
            ];
        }
    }

    public function save(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('operations.shipments.manage'), 403);

        $this->validate([
            'selectedOrderStatusId' => ['required', 'integer', 'exists:order_statuses,id'],
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'client_id' => ['nullable', 'integer', 'exists:entities,id'],
            'client_machine_id' => ['nullable', 'integer', 'exists:client_machines,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $status = OrderStatus::with(['saleOrder', 'customer', 'items.product', 'items.saleOrderItem'])->findOrFail($this->selectedOrderStatusId);
        $quantities = $this->selectedDispatchQuantities($status);

        if ($quantities === []) {
            $this->addError('dispatch', 'Selecciona al menos una partida autorizada para despachar.');

            return;
        }

        if ($this->generate_invoice) {
            $this->validate([
                'invoice_number' => ['required', 'string', 'max:120', 'unique:invoices,invoice_number'],
                'invoice_condition' => ['required', 'in:Contado,30 Días,60 Días,90 Días'],
                'invoice_due_date' => ['required', 'date'],
            ]);
        }

        DB::transaction(function () use ($service, $status, $quantities) {
            $documentPath = 'order-status/logistics/dispatch-'.$status->id.'-'.now()->format('YmdHis').'.txt';
            $service->attachDocument(
                $status,
                null,
                OrderStatusService::PHASE_LOGISTICS,
                'delivery_logistics_document',
                $documentPath,
                auth()->user(),
                $this->notes ?: 'Documento logistico generado desde despacho al cliente.'
            );

            $status = $service->registerDeliveryDispatchBatch($status, $quantities, auth()->user(), $this->notes ?: null);

            foreach ($quantities as $itemId => $quantity) {
                $item = $status->items->firstWhere('id', (int) $itemId);
                if (! $item) {
                    continue;
                }

                $shipment = LogisticsShipment::create([
                    'order_id' => $status->order_code ?: $status->saleOrder?->reference,
                    'order_status_item_id' => $item->id,
                    'sale_order_item_id' => $item->sale_order_item_id,
                    'shipping_cost' => $this->shipping_cost,
                    'currency' => strtoupper($this->currency),
                    'user_id' => auth()->id(),
                    'client_id' => $this->client_id ?: ($status->customer_id ?: $status->saleOrder?->customer_id),
                    'client_machine_id' => $this->client_machine_id ?: null,
                    'product_id' => $item->product_id,
                    'quantity' => (int) $quantity,
                    'shipped_at' => now(),
                ]);

                event(new PackageShippedByCargoTrans(
                    eventId: (string) Str::uuid(),
                    orderId: $shipment->order_id,
                    shippingCost: (string) $shipment->shipping_cost,
                    currency: $shipment->currency,
                    userId: auth()->id(),
                    timestamp: $shipment->shipped_at->toDateTimeString(),
                    clientId: $shipment->client_id,
                    clientMachineId: $shipment->client_machine_id,
                    productId: $shipment->product_id,
                    quantity: (int) $quantity,
                ));
            }

            if ($status->saleOrder && $status->saleOrder->status !== 'entregado') {
                $status->saleOrder->update(['status' => 'en_ruta']);
            }

            if ($this->generate_invoice) {
                $this->createDispatchInvoice($status, $quantities);
            }

            ActivityLog::log(
                'logistica',
                'shipment_created',
                "Despacho registrado desde Order Status {$status->order_code}: ".count($quantities).' partida(s).',
                ['order_status_id' => $status->id, 'items' => $quantities]
            );
        });

        $invoiceWasGenerated = $this->generate_invoice;
        $this->reset(['selectedOrderStatusId', 'selected_order', 'order_items', 'dispatch', 'shipping_cost', 'client_id', 'client_machine_id', 'notes', 'generate_invoice', 'invoice_number']);
        $this->currency = 'USD';
        $this->invoice_condition = 'Contado';
        $this->invoice_due_date = now()->addDays(30)->toDateString();
        $message = $invoiceWasGenerated ? 'Despacho, Packing List y factura registrados.' : 'Despacho y Packing List registrados.';
        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
        session()->flash('message', $message);
    }

    public function exportExcel()
    {
        abort_unless(auth()->user()?->can('operations.shipments.manage'), 403);

        return Excel::download(new ShipmentsExport, 'envios.xlsx');
    }

    public function exportPdf($shipmentId)
    {
        abort_unless(auth()->user()?->can('operations.shipments.manage'), 403);

        $shipment = LogisticsShipment::with(['client', 'product', 'user', 'orderStatusItem'])->findOrFail($shipmentId);
        $pdf = Pdf::loadView('exports.shipment-manifest', compact('shipment'));

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->stream();
        }, 'manifiesto-'.$shipment->order_id.'.pdf');
    }

    public function render()
    {
        return view('livewire.logistica.shipment-manager', [
            'shipments' => LogisticsShipment::with(['client', 'machine', 'user', 'product', 'orderStatusItem'])->latest()->get(),
            'clients' => Entity::where('type', 'cliente')->get(),
            'machines' => ClientMachine::all(),
            'pending_orders' => $this->dispatchableOrderStatuses(),
        ]);
    }

    private function dispatchableOrderStatuses()
    {
        return OrderStatus::with(['saleOrder.customer', 'customer', 'items.product'])
            ->whereHas('items', fn ($query) => $query->whereColumn('quantity_kardex_output', '>', 'quantity_dispatched'))
            ->latest()
            ->get();
    }

    /** @param array<int, int|float> $quantities */
    private function createDispatchInvoice(OrderStatus $status, array $quantities): Invoice
    {
        $status->loadMissing(['saleOrder', 'customer', 'items.product', 'items.saleOrderItem']);

        $lines = collect($quantities)->map(function ($quantity, $itemId) use ($status) {
            $item = $status->items->firstWhere('id', (int) $itemId);
            $unitPrice = (float) ($item?->saleOrderItem?->unit_price ?? 0);

            if (! $item || $unitPrice <= 0) {
                throw ValidationException::withMessages([
                    'invoice_number' => 'No se puede facturar: una de las partidas seleccionadas no tiene precio de venta.',
                ]);
            }

            return [
                'code' => $item->part_number ?: $item->product?->sku ?: '—',
                'description' => $item->part_name ?: $item->product?->name ?: 'Producto',
                'quantity' => (float) $quantity,
                'unit_price' => $unitPrice,
                'total' => round((float) $quantity * $unitPrice, 2),
            ];
        })->values();

        $total = round((float) $lines->sum('total'), 2);
        $customerId = (int) ($this->client_id ?: $status->customer_id ?: $status->saleOrder?->customer_id);
        $path = 'invoices/generated/'.now()->format('Y/m').'/Factura_'.$this->invoice_number.'.pdf';
        $description = 'Despacho '.$status->order_code.': '.$lines->pluck('description')->implode('; ');

        $invoice = Invoice::create([
            'invoice_number' => $this->invoice_number,
            'invoice_type' => 'customer_invoice',
            'entity_id' => $customerId ?: null,
            'customer_id' => $customerId ?: null,
            'sales_order_id' => $status->sale_order_id,
            'order_status_id' => $status->id,
            'date' => now()->toDateString(),
            'due_date' => $this->invoice_due_date,
            'description' => $description,
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $total,
            'amount_nio' => $total,
            'currency' => 'NIO',
            'status' => 'Pendiente',
            'classification' => 'Venta de producto',
            'condition' => $this->invoice_condition,
            'invoice_file_path' => $path,
            'generated_by' => auth()->id(),
            'uploaded_by' => auth()->id(),
        ]);

        $pdf = Pdf::loadView('pdf.dispatch-invoice', compact('invoice', 'status', 'lines'))
            ->setPaper('letter');
        Storage::disk('private')->put($path, $pdf->output());

        if ($this->invoice_condition !== 'Contado' && $customerId) {
            AccountReceivable::create([
                'receivable_code' => 'CXC-'.$this->invoice_number,
                'entity_id' => $customerId,
                'customer_id' => $customerId,
                'sales_order_id' => $status->sale_order_id,
                'order_status_id' => $status->id,
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'concept' => 'Factura '.$invoice->invoice_number.' — despacho '.$status->order_code,
                'classification' => 'Venta de producto',
                'condition' => $this->invoice_condition,
                'invoice_date' => now()->toDateString(),
                'total_amount' => $total,
                'paid_amount' => 0,
                'currency' => 'NIO',
                'due_date' => $this->invoice_due_date,
                'support_document_path' => $path,
                'created_by' => auth()->id(),
                'status' => 'pendiente',
            ]);
        }

        ActivityLog::log('finanzas', 'invoice.generated_from_dispatch', "Factura {$invoice->invoice_number} generada desde despacho {$status->order_code}.", ['invoice_id' => $invoice->id, 'order_status_id' => $status->id]);

        return $invoice;
    }

    private function selectedDispatchQuantities(OrderStatus $status): array
    {
        return collect($this->dispatch)
            ->filter(fn ($entry) => ! empty($entry['selected']) && (int) ($entry['qty'] ?? 0) > 0)
            ->mapWithKeys(fn ($entry, $itemId) => [(int) $itemId => (int) $entry['qty']])
            ->filter(function (int $quantity, int $itemId) use ($status) {
                $item = $status->items->firstWhere('id', $itemId);
                if (! $item || ! $this->canDispatchItem($status, $item)) {
                    return false;
                }

                return $quantity <= $this->pendingDispatchQuantity($item);
            })
            ->all();
    }

    private function pendingDispatchQuantity(OrderStatusItem $item): int
    {
        return max(0, (int) ceil((float) $item->quantity_kardex_output - (float) $item->quantity_dispatched));
    }

    private function canDispatchItem(OrderStatus $status, OrderStatusItem $item): bool
    {
        if ($this->pendingDispatchQuantity($item) <= 0) {
            return false;
        }

        return $this->canShipTogether($status)
            || in_array($item->status, [
                OrderStatusService::STATUS_KARDEX_OUTPUT,
                OrderStatusService::STATUS_KARDEX_OUTPUT_PARTIAL,
                OrderStatusService::STATUS_SENT_CUSTOMER,
            ], true);
    }

    private function canShipTogether(OrderStatus $status): bool
    {
        $activeItems = $status->items->reject(fn (OrderStatusItem $item) => in_array($item->status, [
            OrderStatusService::STATUS_CANCELLED_SUPPLIER,
            OrderStatusService::STATUS_REPLACEMENT_REQUIRED,
        ], true));

        return $activeItems->isNotEmpty()
            && $activeItems->every(fn (OrderStatusItem $item) => (float) $item->quantity_kardex_output > (float) $item->quantity_dispatched);
    }

    private function decisionLabel(OrderStatus $status, OrderStatusItem $item, bool $allowed): string
    {
        if ($this->canShipTogether($status)) {
            return 'Listo para envio conjunto';
        }

        if ($allowed) {
            return 'Autorizado para envio individual';
        }

        return 'Esperando decision en Order Status';
    }
}
