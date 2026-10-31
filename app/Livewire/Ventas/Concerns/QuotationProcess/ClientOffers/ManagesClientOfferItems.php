<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers;

use App\Domain\Ventas\LicitacionesProveedor\Support\SupplierBidResponseRepository;
use App\Models\Entity;
use App\Models\QuotationRequestItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidSelection;
use App\Services\ExchangeRateService;
use App\Services\OrderTrackerService;
use App\Support\Concurrency\OptimisticLock;
use App\Support\Concurrency\StaleModelException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesClientOfferItems
{
    public function updateClientPrice(int $itemId, mixed $value, ?int $version = null): void
    {
        $this->updateItemField($itemId, 'unit_price', $value, $version);
    }

    public function updateClientQuantity(int $itemId, mixed $value, ?int $version = null): void
    {
        $this->updateItemField($itemId, 'quantity', $value, $version);
    }

    public function updateWarrantyType(int $itemId, ?string $value, ?int $version = null): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $item = QuotationRequestItem::find($itemId);
        if (! $item) {
            return;
        }

        $expectedVersion = $version ?? (int) ($item->version ?: 1);
        if ((int) $item->version !== $expectedVersion) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        if (blank($value) || in_array($value, ['Ninguna', 'Sin garantía', 'none'], true)) {
            $value = null;
        }

        $changes = ['warranty_type' => $value];
        if ($value === null) {
            $changes['has_warranty_extension'] = false;
        }

        try {
            app(OptimisticLock::class)->update($item, $expectedVersion, $changes);
        } catch (StaleModelException) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Garantía actualizada.']);
    }

    public function updateWarrantyExtension(int $itemId, bool $value, ?int $version = null): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $item = QuotationRequestItem::find($itemId);
        if (! $item) {
            return;
        }

        $expectedVersion = $version ?? (int) ($item->version ?: 1);
        if ((int) $item->version !== $expectedVersion) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        try {
            app(OptimisticLock::class)->update($item, $expectedVersion, [
                'has_warranty_extension' => $value,
            ]);
        } catch (StaleModelException) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $this->dispatch('notify', ['type' => 'success', 'message' => $value ? 'Extensión Plus (+50%) aplicada.' : 'Extensión Plus removida.']);
    }

    public function removeClientOfferItem(int $itemId, ?int $version = null): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $item = QuotationRequestItem::with('quotationRequest.items')->find($itemId);
        if (! $item || ! $item->quotationRequest) {
            return;
        }

        if ($version === null || (int) $item->version !== $version) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $quote = $item->quotationRequest;
        if (! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya esta aprobada o emitida. Registra una respuesta/cambio del cliente para rehacerla.']);

            return;
        }

        $quote = $this->prepareClientOfferForEditing($quote);
        try {
            app(OptimisticLock::class)->update($item, $version, [
                'is_approved' => false,
                'line_total' => 0,
            ]);
        } catch (StaleModelException) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $this->recalculateClientOfferTotals($quote);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Partida quitada de la oferta.']);
    }

    public function restoreClientOfferItem(int $itemId, ?int $version = null): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $item = QuotationRequestItem::with('quotationRequest.items')->find($itemId);
        if (! $item || ! $item->quotationRequest) {
            return;
        }

        if ($version === null || (int) $item->version !== $version) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $quote = $item->quotationRequest;
        if (! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya está aprobada o emitida. Registra una respuesta/cambio del cliente para rehacerla.']);

            return;
        }

        $quote = $this->prepareClientOfferForEditing($quote);
        try {
            app(OptimisticLock::class)->update($item, $version, [
                'is_approved' => true,
                'line_total' => round((float) $item->quantity * (float) ($item->unit_price ?? 0), 2),
            ]);
        } catch (StaleModelException) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $this->recalculateClientOfferTotals($quote);
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Partida restaurada a la oferta.']);
    }

    public function addClientOfferItem(): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        if (! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya esta aprobada o emitida. Registra una respuesta/cambio del cliente para rehacerla.']);

            return;
        }

        $this->validate([
            'newOfferItemName' => ['required', 'string', 'max:255'],
            'newOfferItemPartNumber' => ['nullable', 'string', 'max:255'],
            'newOfferItemBrand' => ['nullable', 'string', 'max:255'],
            'newOfferItemModel' => ['nullable', 'string', 'max:255'],
            'newOfferItemQuantity' => ['required', 'integer', 'min:1'],
            'newOfferSupplierId' => ['nullable', 'integer', 'exists:entities,id'],
            'newOfferSupplierName' => ['required_without:newOfferSupplierId', 'nullable', 'string', 'max:255'],
            'newOfferSupplierContact' => ['nullable', 'string', 'max:255'],
            'newOfferSupplierUnitCost' => ['required', 'numeric', 'min:0'],
            'newOfferSupplierCurrency' => ['required', 'exists:currencies,code'],
            'newOfferSupplierWarranty' => ['required', 'string', 'max:255'],
            'newOfferSupplierDocuments' => ['nullable', 'array', 'max:10'],
            'newOfferSupplierDocuments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp,xlsx,xls,csv', 'max:10240'],
            'newOfferItemUnitPrice' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ], [
            'newOfferItemName.required' => 'Escriba el producto o partida a agregar.',
            'newOfferItemQuantity.min' => 'La cantidad debe ser mayor a cero.',
            'newOfferItemUnitPrice.min' => 'El precio no puede ser negativo.',
        ]);

        $quote = $this->prepareClientOfferForEditing($quote);
        $quantity = max(1, (int) round((float) $this->newOfferItemQuantity));
        $unitPrice = max(0, round((float) $this->newOfferItemUnitPrice, 2));
        $supplierCost = (string) $this->newOfferSupplierUnitCost;
        $currency = strtoupper($this->newOfferSupplierCurrency);
        $warranty = trim($this->newOfferSupplierWarranty);

        DB::transaction(function () use ($quote, $quantity, $unitPrice, $supplierCost, $currency, $warranty) {
            $supplier = filled($this->newOfferSupplierId)
                ? Entity::where('type', 'proveedor')->findOrFail((int) $this->newOfferSupplierId)
                : Entity::firstOrCreate(
                    ['type' => 'proveedor', 'name' => trim($this->newOfferSupplierName)],
                    ['status' => 'activo', 'contact_name' => trim($this->newOfferSupplierContact) ?: null]
                );

            if (filled($this->newOfferSupplierContact) && $supplier->contact_name !== trim($this->newOfferSupplierContact)) {
                $supplier->forceFill(['contact_name' => trim($this->newOfferSupplierContact)])->save();
            }

            $repository = app(SupplierBidResponseRepository::class);
            $supplierBid = $repository->bidForSupplier($quote, $supplier, auth()->user());
            $response = SupplierBidResponse::create([
                'quotation_request_id' => $quote->id,
                'supplier_bid_id' => $supplierBid?->id,
                'supplier_id' => $supplier->id,
                'response_code' => $repository->nextResponseCode($quote, $supplier),
                'contact_name' => $supplier->contact_name,
                'currency' => $currency,
                'general_warranty' => $warranty,
                'status' => 'received',
                'created_by' => auth()->id(),
            ]);

            if (! empty($this->newOfferSupplierDocuments)) {
                $trackerService = app(OrderTrackerService::class);
                $tracker = $trackerService->trackerForQuotation($quote);

                if (! $tracker) {
                    throw ValidationException::withMessages([
                        'newOfferSupplierDocuments' => 'No se encontró el seguimiento de esta solicitud para almacenar los archivos.',
                    ]);
                }

                foreach ($this->newOfferSupplierDocuments as $document) {
                    $attachment = $trackerService->addAttachment(
                        $tracker,
                        $document,
                        'supplier_bid',
                        "Documento proveedor {$supplier->name}",
                        null,
                        'supplier_bid_responses',
                        $response->id,
                    );

                    $response->forceFill([
                        'attachment_path' => $attachment->file_path,
                        'supplier_document_path' => $attachment->file_path,
                    ])->save();
                }
            }

            $item = QuotationRequestItem::create([
                'quotation_request_id' => $quote->id,
                'part_name' => trim($this->newOfferItemName),
                'description' => trim($this->newOfferItemName),
                'part_number' => trim($this->newOfferItemPartNumber) ?: null,
                'brand' => trim($this->newOfferItemBrand) ?: null,
                'model' => trim($this->newOfferItemModel) ?: null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $quantity * $unitPrice,
                'selected_supplier_id' => $supplier->id,
                'status' => 'manual_offer_item',
                'is_approved' => true,
            ]);

            $item = app(ExchangeRateService::class)->syncSupplierCostInNio($item, $supplierCost, $currency);
            $responseItem = $response->items()->create([
                'quotation_request_item_id' => $item->id,
                'customer_request_item_id' => $item->id,
                'quoted_part_number' => $item->part_number,
                'quoted_description' => $item->part_name,
                'quoted_brand' => $item->brand,
                'quoted_model' => $item->model,
                'quoted_quantity' => $quantity,
                'unit_cost' => $supplierCost,
                'currency' => $currency,
                'warranty' => $warranty,
                'item_status' => 'quoted',
            ]);

            SupplierBidSelection::updateOrCreate(
                ['quotation_request_id' => $quote->id, 'quotation_request_item_id' => $item->id],
                [
                    'supplier_bid_id' => $supplierBid?->id,
                    'customer_request_item_id' => $item->id,
                    'supplier_bid_response_id' => $response->id,
                    'supplier_bid_response_item_id' => $responseItem->id,
                    'supplier_id' => $supplier->id,
                    'selected_supplier_id' => $supplier->id,
                    'selected_quantity' => $quantity,
                    'selected_unit_cost' => $supplierCost,
                    'currency' => $currency,
                    'selected_part_number' => $item->part_number,
                    'selected_brand' => $item->brand,
                    'selected_model' => $item->model,
                    'warranty' => $warranty,
                    'selected_by' => auth()->id(),
                    'selected_at' => now(),
                ]
            );
        });

        $this->recalculateClientOfferTotals($quote);
        $this->newOfferItemName = '';
        $this->newOfferItemPartNumber = '';
        $this->newOfferItemBrand = '';
        $this->newOfferItemModel = '';
        $this->newOfferItemQuantity = '1';
        $this->newOfferSupplierId = '';
        $this->newOfferSupplierName = '';
        $this->newOfferSupplierContact = '';
        $this->newOfferSupplierUnitCost = '0';
        $this->newOfferSupplierCurrency = 'USD';
        $this->newOfferSupplierWarranty = 'Sin garantía';
        $this->newOfferSupplierDocuments = [];
        $this->newOfferItemUnitPrice = '0';
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Partida agregada a la oferta.']);
    }

    public function openCompareSuppliersModal(int $itemId): void
    {
        $this->comparingOfferItemId = $itemId;
    }

    public function closeCompareSuppliersModal(): void
    {
        $this->comparingOfferItemId = null;
    }

    public function applySupplierResponseToAllItems(int $responseId): void
    {
        if ($responseId <= 0) {
            return;
        }

        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        $response = \App\Models\SupplierBidResponse::with(['supplier', 'items'])->find($responseId);
        if (! $response || ! $response->supplier) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontró la preliquidación especificada.']);

            return;
        }

        $applied = 0;
        foreach ($quote->items as $item) {
            $respItem = $response->items->firstWhere('quotation_request_item_id', $item->id);
            if ($respItem && ($respItem->is_selectable || (float) ($respItem->unit_cost ?? 0) > 0)) {
                $this->selectSupplierResponseForItem($item->id, $respItem->id, null);
                $applied++;
            }
        }

        if ($applied > 0) {
            $this->recalculateClientOfferTotals($quote->fresh());
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => "Se aplicó la preliquidación de {$response->supplier->name} a {$applied} partidas.",
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'info',
                'message' => "El proveedor {$response->supplier->name} no tiene partidas cotizadas para esta solicitud.",
            ]);
        }
    }

    public function selectSupplierResponseForItem(int $itemId, int $responseItemId, ?int $version = null): void
    {
        if ($responseItemId <= 0) {
            return;
        }

        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);

            return;
        }

        $item = QuotationRequestItem::with(['quotationRequest', 'supplierBidSelection'])->find($itemId);
        if (! $item || ! $item->quotationRequest) {
            return;
        }

        if ($version !== null && (int) $item->version !== $version) {
            $this->dispatchConcurrentEditConflict();

            return;
        }

        $quote = $item->quotationRequest;
        if (! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya está aprobada o emitida. Registra una respuesta/cambio del cliente para modificarla.']);

            return;
        }

        $responseItem = \App\Models\SupplierBidResponseItem::with(['response.supplier', 'quotationItem'])->find($responseItemId);
        if (! $responseItem || ! $responseItem->response) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se encontró la cotización del proveedor especificada.']);

            return;
        }

        $supplier = $responseItem->response->supplier;
        if (! $supplier) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'El proveedor asociado no es válido.']);

            return;
        }

        $quote = $this->prepareClientOfferForEditing($quote);

        // Resolve preliquidated prices
        $priceUsd = (float) ($responseItem->preliquidated_unit_price ?? 0);
        $priceNio = (float) ($responseItem->preliquidated_unit_price_nio ?? 0);

        if ($priceUsd <= 0 && $priceNio <= 0 && ! empty($responseItem->response->preliquidation)) {
            $calc = app(\App\Services\PreliquidationCalculator::class)->forResponse($responseItem->response);
            $rowCalc = $calc['rows'][$responseItem->id] ?? null;
            if ($rowCalc) {
                $priceUsd = (float) ($rowCalc['assigned_unit'] ?: ($rowCalc['price'] ?? 0));
                $priceNio = (float) ($rowCalc['assigned_unit_nio'] ?: ($rowCalc['price_nio'] ?? 0));
            }
        }

        $targetCurrency = strtoupper((string) ($quote->currency ?: 'NIO'));
        $unitPrice = ($targetCurrency === 'USD' && $priceUsd > 0)
            ? $priceUsd
            : ($priceNio > 0 ? $priceNio : ($priceUsd > 0 ? round($priceUsd * 37.0, 2) : round((float) $responseItem->unit_cost * 1.30, 2)));

        $quantity = (float) $item->quantity;
        $lineTotal = round($quantity * $unitPrice, 2);

        DB::transaction(function () use ($quote, $item, $responseItem, $supplier, $unitPrice, $lineTotal, $priceUsd, $priceNio, $quantity) {
            $selectionData = [
                'supplier_bid_id' => $responseItem->response->supplier_bid_id,
                'customer_request_item_id' => $item->id,
                'supplier_bid_response_id' => $responseItem->supplier_bid_response_id,
                'supplier_bid_response_item_id' => $responseItem->id,
                'supplier_id' => $supplier->id,
                'selected_supplier_id' => $supplier->id,
                'selected_quantity' => $quantity,
                'selected_unit_cost' => $responseItem->unit_cost,
                'currency' => $responseItem->currency ?: $responseItem->response->currency ?: 'USD',
                'selected_brand' => $responseItem->quoted_brand ?: $item->brand,
                'selected_model' => $responseItem->quoted_model ?: $item->model,
                'selected_part_number' => $responseItem->quoted_part_number ?: $item->part_number,
                'delivery_time' => $responseItem->delivery_time ?: $responseItem->response->general_delivery_time,
                'warranty' => $responseItem->warranty ?: $responseItem->response->general_warranty,
                'is_alternative' => (bool) ($responseItem->is_alternative ?? false),
                'selected_by' => auth()->id(),
                'selected_at' => now(),
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn('supplier_bid_selections', 'selected_unit_price')) {
                $selectionData['selected_unit_price'] = $priceUsd > 0 ? $priceUsd : null;
                $selectionData['selected_unit_price_nio'] = $priceNio > 0 ? $priceNio : null;
            }

            SupplierBidSelection::updateOrCreate(
                [
                    'quotation_request_id' => $quote->id,
                    'quotation_request_item_id' => $item->id,
                ],
                $selectionData
            );

            $item->forceFill([
                'selected_supplier_id' => $supplier->id,
                'unit_cost' => $responseItem->unit_cost,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'supplier_delivery_time' => $responseItem->delivery_time ?: $responseItem->response->general_delivery_time,
                'supplier_response_notes' => $responseItem->internal_comment,
                'is_approved' => true,
                'version' => (int) $item->version + 1,
            ])->save();

            app(\App\Services\ExchangeRateService::class)->syncSupplierCostInNio(
                $item,
                $responseItem->unit_cost,
                $responseItem->currency ?: $responseItem->response->currency ?: 'USD'
            );
        });

        $this->recalculateClientOfferTotals($quote);
        $this->comparingOfferItemId = null;
        $currencySymbol = $targetCurrency === 'USD' ? '$' : 'C$';
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => "Proveedor {$supplier->name} seleccionado. Precio oferta: {$currencySymbol} " . number_format($unitPrice, 2),
        ]);
    }
}
