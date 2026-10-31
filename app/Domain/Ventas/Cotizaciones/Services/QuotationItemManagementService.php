<?php

namespace App\Domain\Ventas\Cotizaciones\Services;

use App\Domain\Pedidos\Tracking\Services\OrderTrackerService;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\QuotationRequest;
use App\Models\QuotationRequestItem;
use App\Models\SupplierRfqDocument;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationItemManagementService
{
    public function __construct(
        private readonly OrderTrackerService $trackerService,
        private readonly ExchangeRateService $exchangeRateService,
    ) {}

    /**
     * Actualiza la lista completa de ítems de una cotización (edición, agregados y eliminados).
     *
     * @param array<int, array{
     *     id?: int|null,
     *     part_code: string,
     *     part_name: string,
     *     quantity: int|numeric,
     *     unit?: string|null,
     *     part_brand?: string|null,
     *     part_model?: string|null,
     *     part_serial_number?: string|null,
     *     notes?: string|null,
     *     product_id?: int|null
     * }> $itemsData
     * @param  list<int>  $deletedItemIds
     */
    public function updateItems(
        QuotationRequest $quotation,
        array $itemsData,
        array $deletedItemIds,
        User $user,
        ?string $changeReason = null
    ): QuotationRequest {
        if (empty($itemsData)) {
            throw ValidationException::withMessages([
                'items' => 'La cotización debe contener al menos un ítem.',
            ]);
        }

        return DB::transaction(function () use ($quotation, $itemsData, $deletedItemIds, $user, $changeReason) {
            $lockedQuote = QuotationRequest::whereKey($quotation->id)->lockForUpdate()->firstOrFail();

            $beforeSummary = [
                'items_count' => $lockedQuote->items()->count(),
                'total_quantity' => (float) $lockedQuote->items()->sum('quantity'),
                'items' => $lockedQuote->items()->get()->map(fn ($it) => [
                    'id' => $it->id,
                    'part_number' => $it->part_number,
                    'part_name' => $it->part_name,
                    'quantity' => (float) $it->quantity,
                ])->all(),
            ];

            // 1. Eliminar ítems marcados
            if (! empty($deletedItemIds)) {
                $itemsToDelete = QuotationRequestItem::where('quotation_request_id', $lockedQuote->id)
                    ->whereIn('id', $deletedItemIds)
                    ->get();

                foreach ($itemsToDelete as $itemToDelete) {
                    $itemToDelete->supplierBidItems()->delete();
                    $itemToDelete->supplierBidResponseItems()->delete();
                    $itemToDelete->supplierBidSelection()->delete();
                    $itemToDelete->delete();
                }

                // Saneamiento de RFQ documents que tenían selected_item_ids
                $this->sanitizeRfqDocumentSelections($lockedQuote, $deletedItemIds);
            }

            // 2. Procesar ítems (actualizar o crear)
            $totalQuantity = 0;
            $firstPartCode = null;

            foreach ($itemsData as $itemRow) {
                $partCode = trim((string) ($itemRow['part_code'] ?? ''));
                $partName = trim((string) ($itemRow['part_name'] ?? ''));
                $quantity = max(1, (int) round((float) ($itemRow['quantity'] ?? 1)));
                $unit = trim((string) ($itemRow['unit'] ?? 'PZA')) ?: 'PZA';
                $brand = trim((string) ($itemRow['part_brand'] ?? '')) ?: null;
                $model = trim((string) ($itemRow['part_model'] ?? '')) ?: null;
                $serial = trim((string) ($itemRow['part_serial_number'] ?? '')) ?: null;
                $notes = trim((string) ($itemRow['notes'] ?? '')) ?: null;
                $productId = filled($itemRow['product_id'] ?? null) ? (int) $itemRow['product_id'] : null;
                $supplierCurrency = strtoupper(trim((string) ($itemRow['supplier_currency'] ?? 'USD'))) ?: 'USD';
                $rawSupplierUnitCost = $itemRow['supplier_unit_cost'] ?? 0;
                $rawClientUnitPrice = $itemRow['client_unit_price'] ?? 0;

                if (! is_numeric($rawSupplierUnitCost) || ! is_numeric($rawClientUnitPrice)) {
                    throw ValidationException::withMessages([
                        'items' => 'Los costos y precios deben contener valores numéricos válidos.',
                    ]);
                }

                $supplierUnitCost = max(0, round((float) $rawSupplierUnitCost, 2));
                $clientUnitPrice = max(0, round((float) $rawClientUnitPrice, 2));

                if (! in_array($supplierCurrency, ['USD', 'NIO'], true)) {
                    throw ValidationException::withMessages([
                        'items' => 'La moneda del costo proveedor debe ser USD o NIO.',
                    ]);
                }

                if (blank($partCode) || blank($partName)) {
                    throw ValidationException::withMessages([
                        'items' => 'Todos los ítems deben tener código de parte y descripción válidos.',
                    ]);
                }

                if (! $firstPartCode) {
                    $firstPartCode = $partCode;
                }

                $totalQuantity += $quantity;

                $itemId = filled($itemRow['id'] ?? null) ? (int) $itemRow['id'] : null;

                $supplierId = filled($itemRow['supplier_id'] ?? null) ? (int) $itemRow['supplier_id'] : null;

                if ($itemId) {
                    // Actualizar ítem existente
                    $itemModel = QuotationRequestItem::where('quotation_request_id', $lockedQuote->id)
                        ->whereKey($itemId)
                        ->first();

                    if ($itemModel) {
                        if (! array_key_exists('supplier_unit_cost', $itemRow)) {
                            $supplierUnitCost = (float) ($itemModel->supplier_unit_cost_original ?? $itemModel->unit_cost ?? 0);
                        }
                        if (! array_key_exists('supplier_currency', $itemRow)) {
                            $supplierCurrency = $itemModel->supplier_currency ?: 'USD';
                        }
                        if (! array_key_exists('client_unit_price', $itemRow)) {
                            $clientUnitPrice = (float) ($itemModel->unit_price ?? 0);
                        }

                        $updateData = [
                            'part_number' => $partCode,
                            'part_name' => $partName,
                            'description' => $partName,
                            'quantity' => $quantity,
                            'unit' => $unit,
                            'brand' => $brand,
                            'model' => $model,
                            'serial_number' => $serial,
                            'notes' => $notes,
                            'product_id' => $productId,
                            'unit_price' => $clientUnitPrice,
                            'line_total' => round($quantity * $clientUnitPrice, 2),
                        ];

                        if (array_key_exists('supplier_id', $itemRow)) {
                            $updateData['selected_supplier_id'] = $supplierId;
                        }

                        $itemModel->update($updateData);

                        if (array_key_exists('supplier_unit_cost', $itemRow) || array_key_exists('supplier_currency', $itemRow)) {
                            $this->exchangeRateService->syncSupplierCostInNio($itemModel, (string) $supplierUnitCost, $supplierCurrency);
                        }
                    }
                } else {
                    // Crear nuevo ítem
                    if (! $productId) {
                        $matchedProduct = Product::query()
                            ->where('sku', $partCode)
                            ->orWhere('part_number', $partCode)
                            ->first();
                        $productId = $matchedProduct?->id;
                    }

                    $itemModel = QuotationRequestItem::create([
                        'quotation_request_id' => $lockedQuote->id,
                        'product_id' => $productId,
                        'client_machine_id' => $lockedQuote->client_machine_id,
                        'part_name' => $partName,
                        'part_number' => $partCode,
                        'description' => $partName,
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'notes' => $notes,
                        'unit_price' => $clientUnitPrice,
                        'line_total' => round($quantity * $clientUnitPrice, 2),
                        'internal_request_ref' => $lockedQuote->reference,
                        'brand' => $brand,
                        'model' => $model,
                        'serial_number' => $serial,
                        'machine_brand' => $lockedQuote->machine_brand,
                        'machine_model' => $lockedQuote->machine_model,
                        'image_path' => $lockedQuote->evidence_attachment_path,
                        'status' => 'pending_review',
                        'selected_supplier_id' => $supplierId,
                    ]);

                    $this->exchangeRateService->syncSupplierCostInNio($itemModel, (string) $supplierUnitCost, $supplierCurrency);
                }

                // Sincronizar documento RFQ del proveedor para incluir la partida si corresponde
                if ($itemModel) {
                    if ($supplierId) {
                        $rfqDoc = SupplierRfqDocument::where('quotation_request_id', $lockedQuote->id)
                            ->where('supplier_id', $supplierId)
                            ->first();

                        if ($rfqDoc) {
                            $selectedIds = collect($rfqDoc->selected_item_ids ?: [])
                                ->map(fn ($id) => (int) $id)
                                ->push((int) $itemModel->id)
                                ->unique()
                                ->values()
                                ->all();

                            $rfqQuantities = $rfqDoc->rfq_item_quantities ?: [];
                            $rfqQuantities[$itemModel->id] = $quantity;

                            $rfqDoc->update([
                                'selected_item_ids' => $selectedIds,
                                'rfq_item_quantities' => $rfqQuantities,
                            ]);
                        }
                    } elseif ($lockedQuote->supplierRfqDocuments()->count() === 1) {
                        $singleRfqDoc = $lockedQuote->supplierRfqDocuments()->first();
                        if ($singleRfqDoc && is_array($singleRfqDoc->selected_item_ids) && ! empty($singleRfqDoc->selected_item_ids)) {
                            $selectedIds = collect($singleRfqDoc->selected_item_ids)
                                ->map(fn ($id) => (int) $id)
                                ->push((int) $itemModel->id)
                                ->unique()
                                ->values()
                                ->all();

                            $rfqQuantities = $singleRfqDoc->rfq_item_quantities ?: [];
                            $rfqQuantities[$itemModel->id] = $quantity;

                            $singleRfqDoc->update([
                                'selected_item_ids' => $selectedIds,
                                'rfq_item_quantities' => $rfqQuantities,
                            ]);
                        }
                    }
                }
            }

            // 3. Actualizar resumen de la cotización
            $subtotal = round((float) $lockedQuote->items()->where('is_approved', true)->sum('line_total'), 2);
            $taxPercent = max(0, (float) ($lockedQuote->tax_percent ?? 15));

            $lockedQuote->update([
                'part_code' => $firstPartCode ?: $lockedQuote->part_code,
                'request_quantity' => $totalQuantity,
                'subtotal' => $subtotal,
                'total_amount' => round($subtotal * (1 + ($taxPercent / 100)), 2),
            ]);

            $afterSummary = [
                'items_count' => $lockedQuote->items()->count(),
                'total_quantity' => (float) $lockedQuote->items()->sum('quantity'),
                'items' => $lockedQuote->items()->get()->map(fn ($it) => [
                    'id' => $it->id,
                    'part_number' => $it->part_number,
                    'part_name' => $it->part_name,
                    'quantity' => (float) $it->quantity,
                ])->all(),
            ];

            // 4. Registro de auditoría
            $reasonText = $changeReason ? " Motivo: \"{$changeReason}\"." : '';
            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'quotation_items.updated',
                'module' => 'ventas',
                'description' => "Ítems de cotización {$lockedQuote->reference} modificados por {$user->name}.{$reasonText} Partidas: {$afterSummary['items_count']}, Cantidad total: {$totalQuantity}.",
                'metadata' => [
                    'quotation_id' => $lockedQuote->id,
                    'reference' => $lockedQuote->reference,
                    'reason' => $changeReason,
                    'before' => $beforeSummary,
                    'after' => $afterSummary,
                ],
            ]);

            $tracker = $this->trackerService->trackerForQuotation($lockedQuote);
            if ($tracker) {
                $this->trackerService->recordActivity(
                    $tracker,
                    'quotation_items.updated',
                    "Ítems de cotización actualizados ({$afterSummary['items_count']} partidas).{$reasonText}",
                    $lockedQuote->commercial_phase ?: 'commercial_request',
                    'quotation_requests',
                    $lockedQuote->id,
                    $lockedQuote->status,
                    $lockedQuote->status,
                    $user
                );
            }

            return $lockedQuote->fresh([
                'items.supplierBidSelection.responseItem.response.supplier',
                'items.supplierBidResponseItems.response.supplier',
                'supplierRfqDocuments.supplier',
            ]);
        });
    }

    /**
     * Limpia los IDs de ítems eliminados de los documentos RFQ asociados.
     *
     * @param  list<int>  $deletedItemIds
     */
    private function sanitizeRfqDocumentSelections(QuotationRequest $quotation, array $deletedItemIds): void
    {
        $documents = SupplierRfqDocument::where('quotation_request_id', $quotation->id)->get();

        foreach ($documents as $doc) {
            $selectedIds = collect($doc->selected_item_ids ?: [])
                ->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => in_array($id, $deletedItemIds, true))
                ->values()
                ->all();

            $doc->update(['selected_item_ids' => $selectedIds]);
        }
    }
}
