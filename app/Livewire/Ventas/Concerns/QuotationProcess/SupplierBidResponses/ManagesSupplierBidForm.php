<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses;

use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use Illuminate\Support\Str;

trait ManagesSupplierBidForm
{
    public function updateSupplierBidItemStatus(int $itemId, string $status): void
    {
        if (! in_array($status, ['quoted', 'alternative', 'not_quoted'], true)) {
            return;
        }

        $this->supplierBidItems[$itemId]['item_status'] = $status;
        $this->supplierBidItems[$itemId]['not_quoted'] = $status === 'not_quoted';

        if ($status === 'not_quoted') {
            $this->supplierBidItems[$itemId]['unit_cost'] = '';
            $this->supplierBidItems[$itemId]['warranty'] = 'No aplica';
            return;
        }

        if (($this->supplierBidItems[$itemId]['warranty'] ?? '') === 'No aplica') {
            $this->supplierBidItems[$itemId]['warranty'] = '';
        }

        if ($status !== 'alternative') {
            return;
        }

        $this->supplierBidItems[$itemId] = array_merge($this->supplierBidItems[$itemId] ?? [], [
            'quoted_part_number' => '',
            'quoted_description' => '',
            'quoted_quantity' => '',
            'unit_cost' => '',
            'estimated_arrival_date' => null,
            'delivery_time' => '',
            'brand' => '',
            'part_model' => '',
            'serial_number' => '',
            'warranty' => '',
            'notes' => '',
            'item_status' => 'alternative',
            'not_quoted' => false,
        ]);
    }

    public function updatedSupplierCurrency(): void
    {
        $this->supplierCurrency = strtoupper($this->supplierCurrency);

        if (! \App\Models\Currency::query()->where('code', $this->supplierCurrency)->where('is_active', true)->exists()) {
            $this->supplierCurrency = 'USD';
        }

        try {
            $this->supplierCurrencyExchangeRate = (string) app(\App\Services\ExchangeRateService::class)
                ->rateToNio($this->supplierCurrency)['rate'];
            $this->resetErrorBag('exchange_rate');
        } catch (\Throwable $e) {
            $this->supplierCurrencyExchangeRate = '0';
            report($e);
            $this->addError('exchange_rate', \App\Support\UserMessage::error($e, 'No se pudo consultar la tasa. Revisa la moneda y la fecha.'));
        }
    }

    public function toggleSupplierBidItemDetail(int|string $itemId): void
    {
        $quote = $this->selectedQuote();
        $validBase = is_numeric($itemId) && $quote?->items->contains('id', (int) $itemId);
        $validExtra = is_string($itemId) && isset($this->supplierBidExtraItems[$itemId]);
        if (! $validBase && ! $validExtra) {
            return;
        }

        $this->expandedSupplierBidItemId = $this->expandedSupplierBidItemId === $itemId ? null : $itemId;
    }

    public function addSupplierBidResponseLine(int $quotationItemId): void
    {
        $quoteItem = $this->selectedQuote()?->items->firstWhere('id', $quotationItemId);
        if (! $quoteItem) {
            return;
        }

        $key = 'new_'.Str::uuid()->toString();
        $this->supplierBidExtraItems[$key] = [
            'response_item_id' => null,
            'quotation_request_item_id' => $quotationItemId,
            'quoted_quantity' => 1,
            'unit_cost' => '',
            'item_status' => 'quoted',
            'quoted_part_number' => '',
            'quoted_description' => '',
            'brand' => '',
            'part_model' => '',
            'serial_number' => '',
            'delivery_time' => '',
            'warranty' => '',
            'notes' => '',
            'weight_lb' => 0,
        ];
        $this->dispatch('supplier-bid-line-added', itemKey: $key);
    }

    public function removeSupplierBidResponseLine(string $key): void
    {
        $row = $this->supplierBidExtraItems[$key] ?? null;
        if (! $row) {
            return;
        }

        $persisted = filled($row['response_item_id'] ?? null);
        if ($persisted) {
            $responseId = $this->activeSupplierBidResponseId ?: $this->editingSupplierResponseId;
            $responseItem = SupplierBidResponseItem::withExists('selection')
                ->where('supplier_bid_response_id', $responseId)
                ->findOrFail((int) $row['response_item_id']);
            if ($responseItem->selection_exists) {
                $this->addError("supplierBidExtraItems.{$key}", 'No puedes quitar una línea que ya fue seleccionada para la oferta.');
                return;
            }
            $this->supplierBidDeletedItemIds[] = (int) $responseItem->id;
        }

        unset($this->supplierBidExtraItems[$key]);
        $this->dispatch('supplier-bid-line-removed', itemKey: $key, persisted: $persisted);
    }

    public function addSupplierBidStandaloneItem(): void
    {
        $key = 'new_standalone_'.Str::uuid()->toString();
        $this->supplierBidExtraItems[$key] = [
            'response_item_id' => null,
            'quotation_request_item_id' => null,
            'quoted_quantity' => 1,
            'unit_cost' => '',
            'item_status' => 'quoted',
            'quoted_part_number' => '',
            'quoted_description' => '',
            'brand' => '',
            'part_model' => '',
            'serial_number' => '',
            'delivery_time' => '',
            'warranty' => '',
            'notes' => '',
            'weight_lb' => 0,
            'is_standalone' => true,
        ];
        $this->dispatch('supplier-bid-line-added', itemKey: $key);
    }

    public function resetSupplierBidSelection(): void
    {
        $this->resetSupplierBidForm();
    }

    public function clearSupplierResponseForm(): void
    {
        $this->resetSupplierBidForm();
        $this->bidStep = 2;
    }

    public function editSupplierResponse(int $responseId): void
    {
        $response = SupplierBidResponse::with('items.quotationItem')
            ->where('quotation_request_id', $this->selectedQuoteId)
            ->findOrFail($responseId);
        $this->loadSavedSupplierResponse($response);
    }

    private function loadSavedSupplierResponse(SupplierBidResponse $response): void
    {
        $this->supplierProformas = [];
        $this->supplierItemProformas = [];
        $response->loadMissing('items.quotationItem');
        $this->editingSupplierResponseId = $response->id;
        $this->activeSupplierBidResponseId = $response->id;
        $this->selectedSupplierId = (string) $response->supplier_id;
        $this->supplierContactName = (string) ($response->contact_name ?? '');
        $this->supplierCurrency = (string) ($response->currency ?? 'USD');
        $this->updatedSupplierCurrency();
        $this->supplierPaymentTerms = (string) ($response->payment_terms ?? '');
        $this->supplierDeliveryTime = (string) ($response->general_delivery_time ?? '');
        $this->supplierWarranty = (string) ($response->general_warranty ?: 'Sin garantía');
        $this->supplierValidUntil = $response->valid_until?->format('Y-m-d') ?? '';
        $this->supplierNotes = (string) ($response->notes ?? '');
        $this->loadSupplierBidDefaults();

        $loadedBaseItems = [];
        $this->preliquidation = array_replace(\App\Services\PreliquidationCalculator::defaults(), $response->preliquidation['settings'] ?? []);
        $this->supplierBidItems = [];
        if (($response->preliquidation['settings']['version'] ?? 1) < 2) {
            $oldWeight = $response->items->where('item_status', '!=', 'not_quoted')->sum(fn ($item) => (float) $item->quoted_quantity * (float) ($response->preliquidation['weights'][$item->id] ?? 0));
            foreach (['handling', 'freight'] as $field) {
                if ($oldWeight <= 0 && (float)$this->preliquidation[$field] > 0) $this->preliquidationLegacyCosts[$field] = $this->preliquidation[$field];
                $this->preliquidation[$field] = $oldWeight > 0 ? (float) $this->preliquidation[$field] / $oldWeight : 0;
            }
        }
        $this->preliquidation['version'] = 2;
        $this->preliquidationRevision = \App\Services\SaveSupplierPreliquidation::revision($response);
        $this->preliquidation['exchange'] ??= $this->supplierCurrencyExchangeRate;
        foreach ($response->items as $responseItem) {
            $quoteItem = $responseItem->quotationItem;
            if (! $quoteItem) {
                $key = 'saved_'.$responseItem->id;
                $this->supplierBidExtraItems[$key] = [
                    'weight_lb' => $response->preliquidation['weights'][$responseItem->id] ?? 0,
                    'assigned_usd' => $response->preliquidation['assigned'][$responseItem->id]['usd'] ?? null,
                    'assigned_nio' => $response->preliquidation['assigned'][$responseItem->id]['nio'] ?? null,
                    'quantity' => (float) $responseItem->quoted_quantity,
                    'quoted_quantity' => (float) $responseItem->quoted_quantity,
                    'unit_cost' => (float) ($responseItem->unit_cost ?? 0),
                    'not_quoted' => $responseItem->item_status === 'not_quoted',
                    'item_status' => $responseItem->item_status,
                    'quoted_part_number' => $responseItem->quoted_part_number ?: '',
                    'quoted_description' => $responseItem->quoted_description ?: '',
                    'estimated_arrival_date' => $responseItem->estimated_arrival_date?->format('Y-m-d'),
                    'part_model' => $responseItem->quoted_model ?: '',
                    'serial_number' => $responseItem->quoted_serial_number ?: ($responseItem->quoted_serial ?: ''),
                    'brand' => $responseItem->quoted_brand ?: '',
                    'delivery_time' => $responseItem->delivery_time ?: '',
                    'warranty' => $responseItem->warranty ?: '',
                    'notes' => $responseItem->internal_comment ?: '',
                    'response_item_id' => $responseItem->id,
                    'quotation_request_item_id' => null,
                    'is_standalone' => true,
                ];
                $this->supplierBidItemVersions[$key] = $responseItem->updated_at?->toISOString();
                continue;
            }

            $values = [
                'assigned_usd' => $response->preliquidation['assigned'][$responseItem->id]['usd'] ?? null,
                'assigned_nio' => $response->preliquidation['assigned'][$responseItem->id]['nio'] ?? null,
                'weight_lb' => $response->preliquidation['weights'][$responseItem->id] ?? 0,
                'quantity' => (float) $responseItem->quoted_quantity,
                'quoted_quantity' => (float) $responseItem->quoted_quantity,
                'unit_cost' => (float) ($responseItem->unit_cost ?? 0),
                'not_quoted' => $responseItem->item_status === 'not_quoted',
                'item_status' => $responseItem->item_status,
                'quoted_part_number' => $responseItem->quoted_part_number ?: '',
                'quoted_description' => $responseItem->quoted_description ?: '',
                'estimated_arrival_date' => $responseItem->estimated_arrival_date?->format('Y-m-d'),
                'part_model' => $responseItem->quoted_model ?: '',
                'serial_number' => $responseItem->quoted_serial_number ?: ($responseItem->quoted_serial ?: ''),
                'brand' => $responseItem->quoted_brand ?: '',
                'delivery_time' => $responseItem->delivery_time ?: '',
                'warranty' => $responseItem->warranty ?: '',
                'notes' => $responseItem->internal_comment ?: '',
            ];
            if (! isset($loadedBaseItems[$quoteItem->id])) {
                $this->supplierBidItems[$quoteItem->id] = $values + ['response_item_id' => $responseItem->id];
                $this->supplierBidItemVersions[$quoteItem->id] = $responseItem->updated_at?->toISOString();
                $loadedBaseItems[$quoteItem->id] = true;
            } else {
                $key = 'saved_'.$responseItem->id;
                $this->supplierBidExtraItems[$key] = $values + [
                    'response_item_id' => $responseItem->id,
                    'quotation_request_item_id' => $quoteItem->id,
                ];
                $this->supplierBidItemVersions[$key] = $responseItem->updated_at?->toISOString();
            }
        }

        $quote = $this->selectedQuote();
        if ($quote) {
            $rfqDoc = $quote->supplierRfqDocuments->firstWhere('supplier_id', (int) $response->supplier_id);
            $selectedItemIds = $rfqDoc ? collect($rfqDoc->selected_item_ids ?: [])->map(fn ($id) => (int) $id)->all() : [];

            foreach ($quote->items as $item) {
                if (isset($loadedBaseItems[$item->id])) {
                    continue;
                }

                $isDirectlyAssigned = filled($item->selected_supplier_id) && (int) $item->selected_supplier_id === (int) $response->supplier_id;
                $isInSupplierRfq = ! empty($selectedItemIds) && in_array((int) $item->id, $selectedItemIds, true);
                $isOnlySupplier = blank($item->selected_supplier_id) && $quote->supplierRfqDocuments->count() <= 1;

                if ($isDirectlyAssigned || $isInSupplierRfq || $isOnlySupplier) {
                    $this->supplierBidItems[$item->id] = [
                        'weight_lb' => 0,
                        'quantity' => (float) $item->quantity,
                        'quoted_quantity' => (float) $item->quantity,
                        'unit_cost' => (float) ($item->supplier_unit_cost_original ?? $item->unit_cost ?? 0),
                        'not_quoted' => false,
                        'item_status' => 'quoted',
                        'quoted_part_number' => $item->part_number ?: '',
                        'quoted_description' => $item->part_name ?: ($item->description ?: ''),
                        'estimated_arrival_date' => null,
                        'part_model' => $item->model ?: '',
                        'serial_number' => $item->serial_number ?: '',
                        'brand' => $item->brand ?: '',
                        'delivery_time' => '',
                        'warranty' => '',
                        'notes' => '',
                        'response_item_id' => null,
                    ];
                    $loadedBaseItems[$item->id] = true;
                }
            }
        }

        $this->bidStep = 2;
    }

    private function loadSupplierBidDefaults(): void
    {
        $this->preliquidationRevision = null;
        $this->preliquidationLegacyCosts = [];
        $this->preliquidationPreview = [];
        $this->preliquidationPaste = '';
        $this->preliquidationFile = null;
        $this->preliquidationChange = '';
        $this->preliquidation = \App\Services\PreliquidationCalculator::defaults();
        $quote = $this->selectedQuote();
        if (!$quote) {
            return;
        }

        $this->updatedSupplierCurrency();
        $this->supplierBidItemVersions = [];
        $this->preliquidation['exchange'] = $this->supplierCurrencyExchangeRate;
        $this->supplierBidItems = [];
        $this->supplierBidExtraItems = [];
        $this->supplierBidDeletedItemIds = [];

        foreach ($quote->items as $item) {
            if (filled($this->selectedSupplierId) && filled($item->selected_supplier_id) && (int) $item->selected_supplier_id !== (int) $this->selectedSupplierId) {
                continue;
            }

            $this->supplierBidItems[$item->id] = [
                'weight_lb' => 0,
                'quantity' => (float) $item->quantity,
                'quoted_quantity' => (float) $item->quantity,
                'unit_cost' => (float) ($item->supplier_unit_cost_original ?? $item->unit_cost ?? 0),
                'not_quoted' => false,
                'item_status' => 'quoted',
                'quoted_part_number' => $item->part_number ?: '',
                'quoted_description' => $item->part_name ?: ($item->description ?: ''),
                'estimated_arrival_date' => null,
                'part_model' => $item->model ?: '',
                'serial_number' => $item->serial_number ?: '',
                'brand' => $item->brand ?: '',
                'delivery_time' => '',
                'warranty' => '',
                'notes' => '',
                'response_item_id' => null,
            ];
        }
    }

    private function resetSupplierBidForm(): void
    {
        $this->supplierItemProformas = [];
        $this->selectedSupplierId = '';
        $this->supplierContactName = '';
        $this->supplierCurrency = 'USD';
        $this->updatedSupplierCurrency();
        $this->supplierPaymentTerms = '';
        $this->supplierDeliveryTime = '';
        $this->supplierWarranty = 'Sin garantía';
        $this->supplierValidUntil = '';
        $this->supplierNotes = '';
        $this->responseSupplierName = '';
        $this->supplierProformas = [];
        $this->editingSupplierResponseId = null;
        $this->expandedSupplierBidItemId = null;
        $this->activeSupplierBidResponseId = null;
        $this->loadSupplierBidDefaults();
        $this->resetValidation();
    }
}
