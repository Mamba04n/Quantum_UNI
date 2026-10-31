<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\PurchasePreparationItem;
use App\Models\SupplierBidResponse;
use App\Models\SupplierBidResponseItem;
use App\Services\PurchasePreparationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesPurchasePreparationFlow
{
    public function openPurchasePreparationItem(int $purchasePreparationItemId): void
    {
        $quote = $this->selectedQuote();
        $item = PurchasePreparationItem::query()->find($purchasePreparationItemId);

        if (! $quote || ! $item || $item->quotation_request_id !== $quote->id) {
            return;
        }

        $this->activePurchasePreparationItemId = $item->id;
        $this->activeQuickQuoteItemId = null;
        $this->changeSupplierReason = '';
        $this->selectedFinalSupplierQuote[$item->id] ??= $item->final_supplier_quote_id ?: $item->original_supplier_quote_id;
    }

    public function closePurchasePreparationItem(): void
    {
        $this->activePurchasePreparationItemId = null;
        $this->activeQuickQuoteItemId = null;
        $this->changeSupplierReason = '';
    }

    public function ensurePurchasePreparation(PurchasePreparationService $service): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $service->ensureForQuotation($quote, auth()->user());
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Items enviados a preparacion de compra.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function confirmFinalSupplier(int $purchasePreparationItemId, PurchasePreparationService $service): void
    {
        $item = PurchasePreparationItem::find($purchasePreparationItemId);
        if (! $item) {
            return;
        }

        try {
            $service->confirmSupplier($item, auth()->user());
            $this->closePurchasePreparationItem();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Proveedor final confirmado.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function confirmAllFinalSuppliers(PurchasePreparationService $service): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $confirmed = $service->confirmAllSuppliers($quote, auth()->user());
            $this->closePurchasePreparationItem();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => $confirmed->count().' partida(s) confirmada(s).',
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function changeFinalSupplier(int $purchasePreparationItemId, PurchasePreparationService $service): void
    {
        $quoteId = (int) ($this->selectedFinalSupplierQuote[$purchasePreparationItemId] ?? 0);
        $item = PurchasePreparationItem::find($purchasePreparationItemId);
        $supplierQuote = SupplierBidResponseItem::with('response.supplier')->find($quoteId);

        if (! $item || ! $supplierQuote) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Seleccione una cotizacion proveedor valida.']);

            return;
        }

        try {
            $service->changeFinalSupplier($item, $supplierQuote, auth()->user(), $this->changeSupplierReason);
            $service->confirmSupplier($item->fresh(), auth()->user());
            $this->changeSupplierReason = '';
            unset($this->selectedFinalSupplierQuote[$purchasePreparationItemId]);
            $this->closePurchasePreparationItem();
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Proveedor final actualizado y confirmado con historial.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function addQuickSupplierQuote(int $purchasePreparationItemId, PurchasePreparationService $service): void
    {
        $prepItem = PurchasePreparationItem::find($purchasePreparationItemId);
        if (! $prepItem) {
            return;
        }

        $supplierName = trim($this->quickQuoteSupplierName);
        $productName = trim($this->quickQuoteProductName);
        $unitCost = (float) $this->quickQuoteUnitCost;
        $currency = strtoupper(trim($this->quickQuoteCurrency)) ?: 'USD';
        $warranty = trim($this->quickQuoteWarranty);

        if (blank($supplierName) || $unitCost <= 0 || blank($warranty)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Ingrese proveedor, precio mayor a cero y garantía.']);

            return;
        }

        if (! Currency::query()->where('code', $currency)->where('is_active', true)->exists()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Seleccione una moneda activa del catálogo financiero.']);

            return;
        }

        $supplier = Entity::firstOrCreate(
            ['type' => 'proveedor', 'name' => $supplierName],
            ['status' => 'activo', 'contact_info' => 'Creado automáticamente desde cotización rápida comercial']
        );

        try {
            DB::transaction(function () use ($prepItem, $supplier, $productName, $unitCost, $currency, $warranty, $service) {
                $quoteItem = $prepItem->quotationItem;

                $response = SupplierBidResponse::create([
                    'quotation_request_id' => $prepItem->quotation_request_id,
                    'supplier_id' => $supplier->id,
                    'contact_name' => $supplier->contact_name,
                    'currency' => $currency,
                    'general_warranty' => $warranty,
                    'status' => 'received',
                    'created_by' => auth()->id(),
                    'notes' => 'Cotización rápida agregada desde preparación comercial.',
                ]);

                $supplierQuote = SupplierBidResponseItem::create([
                    'supplier_bid_response_id' => $response->id,
                    'quotation_request_item_id' => $prepItem->quotation_request_item_id,
                    'quoted_part_number' => $quoteItem?->part_number,
                    'quoted_description' => $productName ?: ($quoteItem?->part_name ?: $quoteItem?->description),
                    'quoted_model' => $quoteItem?->model,
                    'quoted_brand' => $quoteItem?->brand,
                    'quoted_quantity' => max(0.01, (float) $prepItem->quantity_to_purchase),
                    'unit_cost' => $unitCost,
                    'currency' => $currency,
                    'warranty' => $warranty,
                    'item_status' => 'quoted',
                    'internal_comment' => 'Cotización rápida agregada comercialmente.',
                ]);

                $service->changeFinalSupplier($prepItem, $supplierQuote, auth()->user(), 'Cambio de proveedor via Cotización Rápida');
                $service->confirmSupplier($prepItem->fresh(), auth()->user());
            });

            $this->activeQuickQuoteItemId = null;
            $this->quickQuoteSupplierName = '';
            $this->quickQuoteProductName = '';
            $this->quickQuoteUnitCost = '';
            $this->quickQuoteWarranty = 'Sin garantía';
            $this->closePurchasePreparationItem();

            $this->dispatch('notify', ['type' => 'success', 'message' => 'Proveedor y precio actualizados.']);
        } catch (\Exception $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => \App\Support\UserMessage::error($e, 'No se pudo actualizar el proveedor. Revisa los datos e intenta de nuevo.')]);
        }
    }

    public function generatePurchaseOrdersBySupplier(PurchasePreparationService $service): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        $this->validate([
            'purchaseOrderCurrency' => ['required', 'in:USD,NIO'],
        ]);

        try {
            $orders = $service->generatePurchaseOrders($quote, auth()->user(), $this->purchaseOrderCurrency);
            $message = $orders->count() === 1
                ? 'Se preparó 1 borrador de orden de compra y se envió a aprobación.'
                : 'Se prepararon '.$orders->count().' borradores de órdenes de compra y se enviaron a aprobación.';

            $this->dispatch('notify', ['type' => 'success', 'message' => $message]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    public function generatePurchaseOrdersFromSelections(): void
    {
        $this->dispatch('notify', ['type' => 'error', 'message' => 'Genere las OC desde Aprobacion Orden de Compra.']);
    }

    public function generateOrder(): mixed
    {
        $this->dispatch('notify', ['type' => 'error', 'message' => 'La orden final se genera desde el modulo formal de aprobaciones.']);

        return null;
    }
}
