<?php

namespace App\Domain\Ventas\OfertasCliente\Support;

use App\Models\QuotationRequest;
use Illuminate\Validation\ValidationException;

class ClientOfferValidator
{
    public function assertOfferComplete(QuotationRequest $quotation): void
    {
        $quotation->loadMissing('items.supplierBidSelection.responseItem.response.supplier');

        if (blank($quotation->quote_to_name)) {
            throw ValidationException::withMessages(['offer' => 'La oferta requiere cliente.']);
        }

        // Una línea retirada durante correcciones queda explícitamente en cero.
        // No forma parte de la oferta ni debe impedir validar las demás líneas.
        $items = $quotation->items
            ->filter(fn ($item) => (bool) $item->is_approved || (float) $item->line_total > 0)
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['offer' => 'La oferta requiere partidas seleccionadas.']);
        }

        foreach ($items as $item) {
            if ((float) ($item->unit_price ?? 0) <= 0) {
                throw ValidationException::withMessages(['offer' => "La partida {$this->lineLabel($item)} no tiene precio cliente."]);
            }

            if ((float) ($item->line_total ?? 0) <= 0) {
                throw ValidationException::withMessages(['offer' => "La partida {$this->lineLabel($item)} no tiene subtotal valido."]);
            }

            $selection = $item->supplierBidSelection;
            if (! $selection && ! $item->selected_supplier_id && (float) ($item->unit_cost ?? 0) <= 0) {
                throw ValidationException::withMessages(['offer' => "La partida {$this->lineLabel($item)} no tiene proveedor/costo base."]);
            }
        }

        $taxPercent = (float) ($quotation->tax_percent ?? 15);
        if ($taxPercent < 0 || $taxPercent > 100) {
            throw ValidationException::withMessages(['offer' => 'El IVA debe estar entre 0 y 100.']);
        }
    }

    private function lineLabel($item): string
    {
        return $item->part_number ?: ($item->part_name ?: 'sin referencia');
    }
}
