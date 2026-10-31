<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses;

use App\Domain\Ventas\LicitacionesProveedor\Services\SupplierBidSpreadsheetParser;

trait ManagesSupplierBidBulkImport
{
    public $supplierBidBulkFile = null;
    public array $supplierBidBulkPreview = [];
    public array $supplierBidBulkMissingItems = [];
    public bool $supplierBidMarkMissingUnavailable = false;
    public bool $showSupplierBidBulkModal = false;

    public function updatedSupplierBidBulkFile(SupplierBidSpreadsheetParser $parser): void
    {
        $this->validate([
            'supplierBidBulkFile' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls'],
        ]);

        try {
            $workbook = $parser->parseWorkbook($this->supplierBidBulkFile->getRealPath());
        } catch (\Throwable $exception) {
            report($exception);
            $workbook = ['metadata' => [], 'rows' => []];
        }

        $quote = $this->selectedQuote();
        if (! $quote) {
            $this->addError('supplierBidBulkFile', 'No se encontró la cotización seleccionada.');
            return;
        }

        $metadata = $workbook['metadata'] ?? [];
        $fileQuoteId = (int) ($metadata['COTIZACION_ID'] ?? 0);
        $fileQuoteRef = trim((string) ($metadata['REFERENCIA'] ?? ''));
        $fileRfqId = (int) ($metadata['RFQ_ID'] ?? 0);
        $fileSupplierId = (int) ($metadata['PROVEEDOR_ID'] ?? 0);

        // 1. Strict validation: File must belong to THIS quotation
        if ($fileQuoteId > 0 && $fileQuoteId !== (int) $quote->id) {
            $otherRef = $fileQuoteRef ? " ({$fileQuoteRef})" : '';
            $this->addError(
                'supplierBidBulkFile',
                "El archivo subido pertenece a otra orden: Cotización #{$fileQuoteId}{$otherRef}. Estás en la Cotización #{$quote->id} ({$quote->reference}). Debes descargar y subir estrictamente la plantilla de esta orden."
            );
            return;
        }

        if ($fileQuoteRef !== '' && strtolower($fileQuoteRef) !== strtolower((string) $quote->reference)) {
            $this->addError(
                'supplierBidBulkFile',
                "El archivo Excel corresponde a la referencia '{$fileQuoteRef}'. La orden actual es '{$quote->reference}'. Por favor sube el archivo generado para esta orden."
            );
            return;
        }

        // 2. Strict validation: If metadata is missing or empty, verify that the items strictly belong to this quotation
        if ($fileQuoteId === 0 && empty($fileQuoteRef)) {
            $quoteItemIds = $quote->items->pluck('id')->map(fn ($id) => (int) $id);
            $fileItemIds = collect($workbook['rows'] ?? [])->pluck('item_id')->map(fn ($id) => (int) $id);
            $hasMatchingItems = $fileItemIds->isNotEmpty() && $fileItemIds->every(fn ($id) => $quoteItemIds->contains($id));

            if (! $hasMatchingItems) {
                $this->addError(
                    'supplierBidBulkFile',
                    "El archivo Excel no contiene la hoja de control ni las partidas de la Cotización {$quote->reference}. Debes utilizar la plantilla oficial descargada desde esta orden."
                );
                return;
            }
        }

        // 3. Match RFQ document and Supplier
        $rfqDocument = null;
        if ($fileRfqId > 0) {
            $rfqDocument = $quote->supplierRfqDocuments->firstWhere('id', $fileRfqId);
            if (! $rfqDocument) {
                $this->addError(
                    'supplierBidBulkFile',
                    "El archivo Excel corresponde a un RFQ (#{$fileRfqId}) que no pertenece a esta orden. Descarga el formato oficial de los proveedores asignados a esta cotización."
                );
                return;
            }
        }

        if (! $rfqDocument && $fileSupplierId > 0) {
            $rfqDocument = $quote->supplierRfqDocuments->firstWhere('supplier_id', $fileSupplierId);
        }

        if (! $rfqDocument) {
            $rfqDocument = $this->supplierRfqDocumentForSelectedSupplier() ?: $quote->supplierRfqDocuments->first();
        }

        if (! $rfqDocument) {
            $this->addError('supplierBidBulkFile', 'No se encontró un RFQ activo para esta orden. Genera primero el RFQ antes de importar respuestas.');
            return;
        }

        // Auto-synchronize the selected supplier with the matching RFQ
        if (filled($rfqDocument->supplier_id)) {
            $this->selectedSupplierId = (string) $rfqDocument->supplier_id;
            $this->supplierContactName = (string) ($rfqDocument->supplier?->contact_name ?: '');
        }

        // Match items
        $allowedItems = $this->itemsForSupplierResponse($quote, $rfqDocument);
        if ($allowedItems->isEmpty()) {
            $allowedItems = $quote->items;
        }
        $allowedIds = $allowedItems->pluck('id')->map(fn ($id) => (int) $id);

        $this->supplierBidBulkPreview = collect($workbook['rows'] ?? [])
            ->filter(fn (array $row) => $allowedIds->contains((int) $row['item_id']))
            ->values()
            ->all();

        // Fallback: If no rows matched allowedIds, try matching against all items of the quotation
        if (empty($this->supplierBidBulkPreview)) {
            $allQuoteIds = $quote->items->pluck('id')->map(fn ($id) => (int) $id);
            $this->supplierBidBulkPreview = collect($workbook['rows'] ?? [])
                ->filter(fn (array $row) => $allQuoteIds->contains((int) $row['item_id']))
                ->values()
                ->all();
            $allowedItems = $quote->items;
            $allowedIds = $allQuoteIds;
        }

        if (empty($this->supplierBidBulkPreview)) {
            $this->addError('supplierBidBulkFile', 'El archivo no contiene partidas con IDs válidos de esta cotización. Asegúrate de usar la plantilla descargada.');
            return;
        }

        $reportedIds = collect($this->supplierBidBulkPreview)->pluck('item_id')->map(fn ($id) => (int) $id);
        $this->supplierBidBulkMissingItems = $allowedItems
            ->reject(fn ($item) => $reportedIds->contains((int) $item->id))
            ->map(fn ($item) => [
                'item_id' => (int) $item->id,
                'part_number' => $item->part_number ?: 'Sin referencia',
                'description' => $item->part_name ?: $item->description,
            ])->values()->all();

        $this->showSupplierBidBulkModal = true;
    }

    public function applySupplierBidBulkImport(): void
    {
        $preview = array_values($this->supplierBidBulkPreview);
        $first = $preview[0] ?? null;
        if (! is_array($first) || empty($preview)) {
            $this->addError('supplierBidBulkFile', 'La vista previa ya no está disponible. Vuelve a seleccionar el archivo Excel.');
            $this->closeSupplierBidBulkImport();
            return;
        }

        $generalWarranty = filled($this->supplierWarranty) ? $this->supplierWarranty : 'Sin garantía';
        $generalDelivery = (string) $this->supplierDeliveryTime;

        foreach ($preview as $row) {
            $itemId = (int) $row['item_id'];
            $current = $this->supplierBidItems[$itemId] ?? [];
            $quantity = is_numeric($row['quoted_quantity']) && (float) $row['quoted_quantity'] > 0
                ? (float) $row['quoted_quantity']
                : (float) ($current['quantity'] ?? 1);

            $isNotQuoted = (bool) ($row['not_quoted'] ?? false) || ($row['item_status'] ?? '') === 'not_quoted';

            $this->supplierBidItems[$itemId] = array_merge($current, [
                'quantity' => $quantity,
                'quoted_quantity' => $quantity,
                'unit_cost' => is_numeric($row['unit_cost']) ? (float) $row['unit_cost'] : '',
                'not_quoted' => $isNotQuoted,
                'item_status' => $row['item_status'] ?? ($isNotQuoted ? 'not_quoted' : 'quoted'),
                'quoted_part_number' => $row['quoted_part_number'] ?? '',
                'quoted_description' => $row['quoted_description'] ?? '',
                'estimated_arrival_date' => $row['estimated_arrival_date'] ?: null,
                'delivery_time' => filled($row['delivery_time'] ?? null) ? $row['delivery_time'] : $generalDelivery,
                'brand' => $row['brand'] ?? '',
                'part_model' => $row['part_model'] ?? '',
                'serial_number' => $row['serial_number'] ?? '',
                'warranty' => $isNotQuoted ? (($row['warranty'] ?? '') ?: 'No aplica') : (($row['warranty'] ?? '') ?: $generalWarranty),
                'notes' => $row['notes'] ?? '',
            ]);
        }

        $missingMarked = 0;
        if ($this->supplierBidMarkMissingUnavailable) {
            foreach ($this->supplierBidBulkMissingItems as $missing) {
                $itemId = (int) $missing['item_id'];
                $current = $this->supplierBidItems[$itemId] ?? [];
                $this->supplierBidItems[$itemId] = array_merge($current, [
                    'unit_cost' => '',
                    'not_quoted' => true,
                    'item_status' => 'not_quoted',
                    'warranty' => 'No aplica',
                    'notes' => 'No disponible / no incluido en la respuesta del proveedor.',
                ]);
                $missingMarked++;
            }
        }

        if (in_array($first['currency'] ?? '', ['NIO', 'USD', 'EUR', 'INR'], true)) {
            $this->supplierCurrency = $first['currency'];
            $this->updatedSupplierCurrency();
        }
        $this->supplierPaymentTerms = ($first['payment_terms'] ?? '') ?: $this->supplierPaymentTerms;
        $this->supplierValidUntil = ($first['valid_until'] ?? '') ?: $this->supplierValidUntil;

        $count = count($preview);
        $importedItemIds = collect($preview)->pluck('item_id')->map(fn ($id) => (int) $id);
        if ($this->supplierBidMarkMissingUnavailable) {
            $importedItemIds = $importedItemIds->merge(
                collect($this->supplierBidBulkMissingItems)->pluck('item_id')->map(fn ($id) => (int) $id)
            );
        }
        $this->closeSupplierBidBulkImport();
        $this->dispatch('supplier-bid-draft-loaded', itemIds: $importedItemIds->unique()->values()->all());
        $suffix = $missingMarked ? " ({$missingMarked} partidas ausentes marcadas como no disponibles)" : '';
        $this->dispatch('notify', ['type' => 'info', 'message' => "Se cargaron {$count} partida(s) en el formulario borrador{$suffix}. Recuerda hacer clic en Guardar cuando termines."]);
    }

    public function saveSupplierBidBulkImport(\App\Services\SupplierBidResponseService $bidService): void
    {
        $preview = array_values($this->supplierBidBulkPreview);
        $first = $preview[0] ?? null;
        if (! is_array($first) || empty($preview)) {
            $this->addError('supplierBidBulkFile', 'La vista previa ya no está disponible. Vuelve a seleccionar el archivo Excel.');
            $this->closeSupplierBidBulkImport();
            return;
        }

        $generalWarranty = filled($this->supplierWarranty) ? $this->supplierWarranty : 'Sin garantía';
        $generalDelivery = (string) $this->supplierDeliveryTime;

        foreach ($preview as $row) {
            $itemId = (int) $row['item_id'];
            $current = $this->supplierBidItems[$itemId] ?? [];
            $quantity = is_numeric($row['quoted_quantity']) && (float) $row['quoted_quantity'] > 0
                ? (float) $row['quoted_quantity']
                : (float) ($current['quantity'] ?? 1);

            $isNotQuoted = (bool) ($row['not_quoted'] ?? false) || ($row['item_status'] ?? '') === 'not_quoted';

            $this->supplierBidItems[$itemId] = array_merge($current, [
                'quantity' => $quantity,
                'quoted_quantity' => $quantity,
                'unit_cost' => is_numeric($row['unit_cost']) ? (float) $row['unit_cost'] : '',
                'not_quoted' => $isNotQuoted,
                'item_status' => $row['item_status'] ?? ($isNotQuoted ? 'not_quoted' : 'quoted'),
                'quoted_part_number' => $row['quoted_part_number'] ?? '',
                'quoted_description' => $row['quoted_description'] ?? '',
                'estimated_arrival_date' => $row['estimated_arrival_date'] ?: null,
                'delivery_time' => filled($row['delivery_time'] ?? null) ? $row['delivery_time'] : $generalDelivery,
                'brand' => $row['brand'] ?? '',
                'part_model' => $row['part_model'] ?? '',
                'serial_number' => $row['serial_number'] ?? '',
                'warranty' => $isNotQuoted ? (($row['warranty'] ?? '') ?: 'No aplica') : (($row['warranty'] ?? '') ?: $generalWarranty),
                'notes' => $row['notes'] ?? '',
            ]);
        }

        if ($this->supplierBidMarkMissingUnavailable) {
            foreach ($this->supplierBidBulkMissingItems as $missing) {
                $itemId = (int) $missing['item_id'];
                $current = $this->supplierBidItems[$itemId] ?? [];
                $this->supplierBidItems[$itemId] = array_merge($current, [
                    'unit_cost' => '',
                    'not_quoted' => true,
                    'item_status' => 'not_quoted',
                    'warranty' => 'No aplica',
                    'notes' => 'No disponible / no incluido en la respuesta del proveedor.',
                ]);
            }
        }

        if (in_array($first['currency'] ?? '', ['NIO', 'USD', 'EUR', 'INR'], true)) {
            $this->supplierCurrency = $first['currency'];
            $this->updatedSupplierCurrency();
        }
        $this->supplierPaymentTerms = ($first['payment_terms'] ?? '') ?: $this->supplierPaymentTerms;
        $this->supplierValidUntil = ($first['valid_until'] ?? '') ?: $this->supplierValidUntil;

        $importedItemIds = collect($preview)->pluck('item_id')->map(fn ($id) => (int) $id);
        if ($this->supplierBidMarkMissingUnavailable) {
            $importedItemIds = $importedItemIds->merge(
                collect($this->supplierBidBulkMissingItems)->pluck('item_id')->map(fn ($id) => (int) $id)
            );
        }

        $this->closeSupplierBidBulkImport();
        $this->saveSupplierResponseDraft($importedItemIds->unique()->values()->all(), $bidService);
    }

    public function closeSupplierBidBulkImport(): void
    {
        $this->showSupplierBidBulkModal = false;
        $this->supplierBidBulkFile = null;
        $this->supplierBidBulkPreview = [];
        $this->supplierBidBulkMissingItems = [];
        $this->supplierBidMarkMissingUnavailable = false;
        $this->resetErrorBag('supplierBidBulkFile');
    }
}
