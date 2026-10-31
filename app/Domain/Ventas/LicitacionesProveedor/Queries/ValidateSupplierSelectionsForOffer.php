<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Queries;

use App\Models\QuotationRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ValidateSupplierSelectionsForOffer
{
    public function execute(QuotationRequest $request): Collection
    {
        $request->loadMissing('supplierBidSelections.responseItem.response.supplier', 'supplierBidSelections.quotationItem', 'items');
        $selections = $request->supplierBidSelections;

        $excludedItems = $request->items->where('supplier_selection_status', 'excluded');
        $activeItems = $request->items->reject(fn ($item) => $item->supplier_selection_status === 'excluded');

        if ($selections->isEmpty() || $activeItems->isEmpty()) {
            throw ValidationException::withMessages(['selection' => 'No puedes avanzar a Oferta Cliente sin productos seleccionados.']);
        }

        if ($excludedItems->contains(fn ($item) => blank($item->supplier_selection_exclusion_reason))) {
            throw ValidationException::withMessages(['selection' => 'Toda partida excluida debe indicar el motivo de la exclusión.']);
        }

        $duplicateSelections = $selections->groupBy('quotation_request_item_id')->filter(fn ($group) => $group->count() !== 1);
        if ($duplicateSelections->isNotEmpty()) {
            throw ValidationException::withMessages(['selection' => 'Cada partida solo puede tener un proveedor seleccionado.']);
        }

        $selectedItemIds = $selections->pluck('quotation_request_item_id')->map(fn ($id) => (int) $id);
        $undecided = $activeItems->filter(fn ($item) => ! $selectedItemIds->contains((int) $item->id));
        if ($undecided->isNotEmpty()) {
            $references = $undecided->map(fn ($item) => $item->part_number ?: $item->part_name ?: "Partida #{$item->id}")->implode(', ');
            throw ValidationException::withMessages(['selection' => "Debes seleccionar un proveedor o excluir con motivo cada partida. Pendientes: {$references}."]);
        }

        foreach ($selections as $selection) {
            if ($selection->quotationItem?->supplier_selection_status === 'excluded') {
                throw ValidationException::withMessages(['selection' => 'Una partida excluida no puede conservar proveedor seleccionado.']);
            }
            if (!$selection->selected_supplier_id) {
                throw ValidationException::withMessages(['selection' => 'Hay una partida seleccionada sin proveedor.']);
            }

            if ((float) $selection->selected_quantity <= 0) {
                throw ValidationException::withMessages(['selection' => 'Hay una partida seleccionada con cantidad invalida.']);
            }

            if ((float) $selection->selected_unit_cost <= 0) {
                throw ValidationException::withMessages(['selection' => 'Hay una partida seleccionada sin precio proveedor valido.']);
            }

            if (blank($selection->delivery_time) && blank($selection->estimated_arrival_date)) {
                throw ValidationException::withMessages(['selection' => 'Hay una partida seleccionada sin fecha o tiempo estimado de llegada.']);
            }

            if ($selection->selected_quantity > (float) $selection->responseItem->quoted_quantity) {
                throw ValidationException::withMessages(['selection' => 'Una partida seleccionada supera la cantidad cotizada.']);
            }

            if ((bool) config('sales.require_supplier_proforma', false) && blank($selection->responseItem->response->attachment_path)) {
                throw ValidationException::withMessages(['selection' => 'Hay proveedores seleccionados sin documento/proforma adjunta.']);
            }
        }

        return $selections;
    }
}
