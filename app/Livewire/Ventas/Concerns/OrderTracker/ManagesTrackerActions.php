<?php

namespace App\Livewire\Ventas\Concerns\OrderTracker;

use App\Domain\Ventas\Seguimiento\Actions\DeleteTrackerItem;
use App\Domain\Ventas\Seguimiento\Actions\MoveTrackerItem;
use App\Domain\Ventas\Seguimiento\Actions\UpdateTrackerItemRow;
use App\Models\QuotationRequest;
use Illuminate\Validation\ValidationException;

trait ManagesTrackerActions
{
    public function moveItem($id, $type, $newStatus)
    {
        abort_unless(auth()->user()?->can('tracker.change_phase'), 403);
        $this->errorMessage = '';

        try {
            app(MoveTrackerItem::class)->execute($id, $type, $newStatus, auth()->user());
            $this->closeModal();
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first();
        }
    }

    public function updateItemRow($itemId, $field, $value)
    {
        $permission = match ($field) {
            'quantity' => 'order_approval.adjust_quantity',
            'unit_price' => 'supplier_bid.add_supplier_response',
            default => 'supplier_bid.add_supplier_response',
        };
        abort_unless(auth()->user()?->can($permission), 403);

        app(UpdateTrackerItemRow::class)->execute($itemId, $field, $value);

        if ($this->selectedItem && $this->itemType === 'quote') {
            $this->selectedItem = QuotationRequest::with('items')->find($this->selectedItem->id);
        }
    }

    public function deleteItem($id, $type)
    {
        abort_unless(auth()->user()?->can($type === 'quote' ? 'customer_request.cancel' : 'direct_order.cancel'), 403);
        $this->errorMessage = '';

        try {
            app(DeleteTrackerItem::class)->execute($id, $type);
            $this->closeModal();
            session()->flash('message', 'Registro cancelado. Inventario actualizado.');
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first();
        }
    }
}
