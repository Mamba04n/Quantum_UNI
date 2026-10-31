<?php

namespace App\Livewire\Bodega;

use App\Models\OrderStatusItem;
use App\Services\OrderStatusService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Validation\ValidationException;
use Throwable;

#[Layout('layouts.app')]
class IncomingOrders extends Component
{
    use WithPagination;
    use WithFileUploads;

    public string $search = '';
    public string $statusFilter = '';
    public ?int $receivingItemId = null;
    public int $quantity = 1;
    public bool $confirmQuantityMismatch = false;
    public string $warehouseCondition = 'bueno';
    public string $warehouseLocation = '';
    public string $notes = '';
    public $warehouseEvidenceFile;
    public $warehousePhotoFile;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedQuantity(): void
    {
        $this->confirmQuantityMismatch = false;
    }

    public function startReception(int $itemId): void
    {
        $item = OrderStatusItem::findOrFail($itemId);
        $this->receivingItemId = $item->id;
        $this->quantity = max(1, $this->pendingReceptionForItem($item));
        $this->confirmQuantityMismatch = false;
        $this->warehouseCondition = 'bueno';
        $this->warehouseLocation = '';
        $this->notes = '';
        $this->reset(['warehouseEvidenceFile', 'warehousePhotoFile']);
    }

    public function cancelReception(): void
    {
        $this->resetReceptionForm();
    }

    public function registerReception(OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('warehouse_receipt.register') || auth()->user()?->can('warehouse.inventory.view'), 403);

        $this->validate([
            'receivingItemId' => ['required', 'integer', 'exists:order_status_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'warehouseCondition' => ['required', 'in:bueno,danado,incompleto,pendiente_revision'],
            'warehouseLocation' => ['nullable', 'string', 'max:120'],
            'warehouseEvidenceFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'warehousePhotoFile' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = OrderStatusItem::whereKey($this->receivingItemId)->firstOrFail();

        if (! $this->canReceiveItem($item)) {
            $this->addError('receivingItemId', 'La partida debe estar liberada de aduana o pertenecer a una compra interna.');
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La partida debe estar liberada de aduana o pertenecer a una compra interna.']);
            return;
        }

        $pendingReception = $this->pendingReceptionForItem($item);
        if ($this->quantity > $pendingReception) {
            $message = "No puedes recibir {$this->quantity}; la cantidad pendiente es {$pendingReception}.";
            $this->addError('quantity', $message);
            $this->dispatch('notify', ['type' => 'error', 'message' => $message]);
            return;
        }

        if ($this->quantity !== $pendingReception && ! $this->confirmQuantityMismatch) {
            $message = "La cantidad recibida ({$this->quantity}) no coincide con la pendiente ({$pendingReception}). Si quieres continuar con recepcion parcial, presiona registrar otra vez.";
            $this->confirmQuantityMismatch = true;
            $this->addError('quantity', $message);
            $this->dispatch('notify', ['type' => 'warning', 'message' => $message]);
            return;
        }

        try {
            $service->receiveItemAndCreateKardexEntry(
                $item,
                $this->quantity,
                (float) ($item->purchaseOrderItem?->unit_price ?? $item->product?->cost ?? 0),
                auth()->user(),
                $this->notes ?: null,
                $this->warehouseEvidenceFile,
                $this->warehousePhotoFile,
                $this->warehouseLocation ?: null,
                $this->warehouseCondition
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'No se pudo registrar la recepcion.';
            $this->addError('receivingItemId', $message);
            $this->dispatch('notify', ['type' => 'error', 'message' => $message]);
            return;
        } catch (Throwable $exception) {
            report($exception);
            $message = \App\Support\UserMessage::error($exception, 'No se pudo registrar la recepción. Revisa los datos e intenta de nuevo.');
            $this->addError('receivingItemId', $message);
            $this->dispatch('notify', ['type' => 'error', 'message' => $message]);
            return;
        }

        $this->resetReceptionForm();
        $message = 'Recepción registrada. El producto está disponible para salida.';
        session()->flash('message', $message);
        $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
    }

    public function render()
    {
        abort_unless(auth()->user()?->can('incoming_orders.view') || auth()->user()?->can('warehouse.inventory.view'), 403);

        $expectedStatuses = [
            OrderStatusService::STATUS_IN_TRANSIT,
            OrderStatusService::STATUS_IN_CUSTOMS,
            OrderStatusService::STATUS_CUSTOMS_RELEASED,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL,
            OrderStatusService::STATUS_KARDEX_ENTRY,
            OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL,
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
            OrderStatusService::STATUS_PO_GENERATED,
        ];

        $items = OrderStatusItem::query()
            ->with(['orderStatus.saleOrder.customer', 'orderStatus.purchaseOrder', 'product', 'supplier', 'purchaseOrderItem'])
            ->whereIn('status', $expectedStatuses)
            ->when($this->statusFilter, fn ($query) => $query->where('status', $this->statusFilter))
            ->when($this->search, function ($query) {
                $term = "%{$this->search}%";

                $query->where(function ($q) use ($term) {
                    $q->where('part_name', 'like', $term)
                        ->orWhere('part_number', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhereHas('orderStatus', fn ($status) => $status
                            ->where('operational_tracking_code', 'like', $term)
                            ->orWhere('order_code', 'like', $term))
                        ->orWhereHas('orderStatus.saleOrder', fn ($order) => $order
                            ->where('reference', 'like', $term)
                            ->orWhere('client_name', 'like', $term));
                });
            })
            ->orderByRaw('estimated_arrival_date is null')
            ->orderBy('estimated_arrival_date')
            ->latest()
            ->paginate(12);

        $receivingItem = $this->receivingItemId
            ? OrderStatusItem::with(['orderStatus.saleOrder.customer', 'orderStatus.purchaseOrder', 'product', 'supplier', 'purchaseOrderItem'])->find($this->receivingItemId)
            : null;

        return view('livewire.bodega.incoming-orders', [
            'items' => $items,
            'receivingItem' => $receivingItem,
            'statuses' => [
                OrderStatusService::STATUS_IN_TRANSIT => 'En transito',
                OrderStatusService::STATUS_IN_CUSTOMS => 'En aduana',
                OrderStatusService::STATUS_CUSTOMS_RELEASED => 'Liberado aduana',
                OrderStatusService::STATUS_RECEIVED_WAREHOUSE => 'Recibido en bodega',
                OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL => 'Recibido parcial',
                OrderStatusService::STATUS_KARDEX_ENTRY => 'Entrada Kardex',
                OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL => 'Entrada Kardex parcial',
                OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH => 'Disponible para salida',
            ],
        ]);
    }

    private function resetReceptionForm(): void
    {
        $this->reset(['receivingItemId', 'warehouseLocation', 'notes', 'warehouseEvidenceFile', 'warehousePhotoFile']);
        $this->quantity = 1;
        $this->confirmQuantityMismatch = false;
        $this->warehouseCondition = 'bueno';
    }

    public function pendingReceptionForItem(OrderStatusItem $item): int
    {
        $receivableQuantity = (float) $item->quantity_shipped > 0
            ? (float) $item->quantity_shipped
            : (float) $item->quantity_ordered;

        return (int) max(0, $receivableQuantity - (float) $item->quantity_received);
    }

    public function canReceiveItem(OrderStatusItem $item): bool
    {
        $isInternalPurchase = (bool) $item->orderStatus?->purchase_order_id;

        return $isInternalPurchase || in_array($item->status, [
            OrderStatusService::STATUS_IN_CUSTOMS,
            OrderStatusService::STATUS_CUSTOMS_RELEASED,
        ], true);
    }
}
