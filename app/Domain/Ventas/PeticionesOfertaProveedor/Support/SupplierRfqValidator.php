<?php

namespace App\Domain\Ventas\PeticionesOfertaProveedor\Support;

use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use Illuminate\Validation\ValidationException;

class SupplierRfqValidator
{
    public static function assertAllItemsHaveSentRfq(QuotationRequest $quotation): void
    {
        $quotation->loadMissing(['items', 'supplierRfqDocuments']);

        $itemIds = $quotation->items
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($itemIds->isEmpty()) {
            throw ValidationException::withMessages(['rfq' => 'No se puede avanzar sin partidas en la solicitud.']);
        }

        $sentDocuments = $quotation->supplierRfqDocuments
            ->where('status', SupplierRfqDocument::STATUS_SENT_TO_SUPPLIER);

        $coveredItemIds = $sentDocuments
            ->flatMap(function (SupplierRfqDocument $document) use ($itemIds) {
                $selected = collect($document->selected_item_ids ?: [])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values();

                return $selected->isNotEmpty() ? $selected : $itemIds;
            })
            ->unique()
            ->values();

        $missingItemIds = $itemIds->diff($coveredItemIds);
        if ($missingItemIds->isEmpty()) {
            return;
        }

        $missingLabels = $quotation->items
            ->whereIn('id', $missingItemIds->all())
            ->map(fn ($item) => trim((string) ($item->part_name ?: $item->description ?: $item->part_number ?: 'Item #'.$item->id)))
            ->filter()
            ->values()
            ->all();

        throw ValidationException::withMessages([
            'rfq' => 'Faltan items por cotizar antes de avanzar: '.implode(', ', $missingLabels).'.',
        ]);
    }

    public static function assertReadyForOfficialRfq(SupplierRfqDocument $document): void
    {
        $required = [
            'quote_to_name' => 'Cotizar a nombre de',
            'effective_response_requirements' => 'Se solicita incluir en su respuesta',
        ];

        foreach ($required as $field => $label) {
            if (blank($document->{$field})) {
                throw ValidationException::withMessages([$field => "El campo {$label} es obligatorio para generar RFQ."]);
            }
        }

        if (filled($document->consignee_emails)) {
            foreach (self::emails($document->consignee_emails) as $email) {
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw ValidationException::withMessages(['consignee_emails' => "Correo invalido: {$email}. Usa correos separados por coma."]);
                }
            }
        }

        $selectedItemIds = collect($document->selected_item_ids ?: [])->filter()->values();
        if ($selectedItemIds->isEmpty()) {
            throw ValidationException::withMessages(['selectedRfqItemIds' => 'Selecciona al menos un item para generar el RFQ.']);
        }

        $validItemIds = $document->quotationRequest?->items()->pluck('id')->map(fn ($id) => (string) $id) ?? collect();
        $invalid = $selectedItemIds->map(fn ($id) => (string) $id)->diff($validItemIds);
        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages(['selectedRfqItemIds' => 'El RFQ contiene items que no pertenecen a esta solicitud.']);
        }
    }

    private static function emails(?string $emails): array
    {
        return collect(explode(',', (string) $emails))
            ->map(fn (string $email) => trim($email))
            ->filter()
            ->values()
            ->all();
    }
}
