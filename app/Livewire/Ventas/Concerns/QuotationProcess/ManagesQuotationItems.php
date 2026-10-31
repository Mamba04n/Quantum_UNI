<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess;

use App\Domain\Ventas\Cotizaciones\Services\BulkItemParserService;
use App\Domain\Ventas\Cotizaciones\Services\QuotationItemManagementService;
use Illuminate\Validation\ValidationException;

trait ManagesQuotationItems
{
    public bool $showManageItemsModal = false;

    public array $editableQuotationItems = [];

    public array $deletedQuotationItemIds = [];

    public string $quotationItemEditorStage = 'customer_request';

    public string $quotationItemChangeReason = '';

    public bool $showBulkImportModal = false;

    public string $bulkImportText = '';

    public array $bulkImportPreview = [];

    public function openManageItemsModal(): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay una cotización seleccionada.']);
            return;
        }

        $this->deletedQuotationItemIds = [];
        $this->quotationItemChangeReason = '';
        $this->quotationItemEditorStage = $this->stageKeyForStatus($quote->status);
        $defaultSupplierId = filled($this->selectedSupplierId ?? null)
            ? (int) $this->selectedSupplierId
            : ($quote->supplierRfqDocuments->first(fn ($d) => filled($d->supplier_id))?->supplier_id ?? '');

        $this->editableQuotationItems = $quote->items->map(fn ($it) => [
            'id' => $it->id,
            'part_code' => $it->part_number ?: $it->part_name,
            'part_name' => $it->part_name ?: $it->description,
            'quantity' => (int) round((float) $it->quantity),
            'unit' => $it->unit ?: 'PZA',
            'part_brand' => $it->brand ?? '',
            'part_model' => $it->model ?? '',
            'part_serial_number' => $it->serial_number ?? '',
            'notes' => $it->notes ?? '',
            'product_id' => $it->product_id,
            'supplier_currency' => $it->supplier_currency ?: 'USD',
            'supplier_unit_cost' => (string) ($it->supplier_unit_cost_original ?? $it->unit_cost ?? '0'),
            'client_unit_price' => (string) ($it->unit_price ?? '0'),
            'supplier_id' => $it->selected_supplier_id ?: $defaultSupplierId,
        ])->values()->all();

        if (empty($this->editableQuotationItems)) {
            $this->editableQuotationItems[] = $this->blankQuotationItem();
        }

        $this->showManageItemsModal = true;
    }

    public function closeManageItemsModal(): void
    {
        $this->showManageItemsModal = false;
        $this->editableQuotationItems = [];
        $this->deletedQuotationItemIds = [];
        $this->quotationItemChangeReason = '';
    }

    public function addEditableQuotationItem(): void
    {
        $this->editableQuotationItems[] = $this->blankQuotationItem();
    }

    public function removeEditableQuotationItem(int $index): void
    {
        if (count($this->editableQuotationItems) <= 1) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'La cotización debe tener al menos una partida.']);
            return;
        }

        if (isset($this->editableQuotationItems[$index])) {
            $item = $this->editableQuotationItems[$index];
            if (! empty($item['id'])) {
                $this->deletedQuotationItemIds[] = (int) $item['id'];
            }
            unset($this->editableQuotationItems[$index]);
            $this->editableQuotationItems = array_values($this->editableQuotationItems);
        }
    }

    public string $bulkMode = 'file';

    public $bulkExcelFile = null;

    public function openBulkImportModalForQuote(): void
    {
        $this->bulkMode = 'file';
        $this->bulkExcelFile = null;
        $this->bulkImportText = '';
        $this->bulkImportPreview = [];
        $this->showBulkImportModal = true;
    }

    public function closeBulkImportModal(): void
    {
        $this->showBulkImportModal = false;
        $this->bulkExcelFile = null;
        $this->bulkImportText = '';
        $this->bulkImportPreview = [];
        $this->bulkMode = 'file';
    }

    public function setBulkMode(string $mode): void
    {
        $this->bulkMode = in_array($mode, ['file', 'text'], true) ? $mode : 'file';
    }

    public function updatedBulkExcelFile(): void
    {
        if (! $this->bulkExcelFile) {
            return;
        }

        $this->validate([
            'bulkExcelFile' => 'required|file|max:10240|mimes:xlsx,xls,csv,txt',
        ], [
            'bulkExcelFile.mimes' => 'El archivo debe ser un formato válido de Excel (.xlsx, .xls) o CSV (.csv).',
            'bulkExcelFile.max' => 'El archivo no puede exceder los 10MB.',
        ]);

        $parser = app(BulkItemParserService::class);
        $this->bulkImportPreview = $parser->parseUploadedFile($this->bulkExcelFile);

        $count = count($this->bulkImportPreview);
        if ($count > 0) {
            $this->dispatch('notify', ['type' => 'success', 'message' => "Se detectaron {$count} partida(s) en el archivo Excel."]);
        } else {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'El archivo no contiene partidas válidas. Revisa su contenido.']);
        }
    }

    public function clearBulkFile(): void
    {
        $this->bulkExcelFile = null;
        $this->bulkImportPreview = [];
    }

    public function updatedBulkImportText(string $value): void
    {
        $parser = app(BulkItemParserService::class);
        $this->bulkImportPreview = $parser->parse($value);
    }

    public function previewBulkImportText(BulkItemParserService $parser): void
    {
        $this->bulkImportPreview = $parser->parse($this->bulkImportText);
        if (empty($this->bulkImportPreview)) {
            $this->dispatch('notify', ['type' => 'warning', 'message' => 'No se detectaron partidas válidas en el texto pegado.']);
        }
    }

    public function applyBulkImportToEditableItems(BulkItemParserService $parser): void
    {
        $items = $this->bulkImportPreview;

        if (empty($items) && filled($this->bulkImportText)) {
            $items = $parser->parse($this->bulkImportText);
        }

        if (empty($items)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No hay partidas válidas para agregar. Revisa el archivo.']);
            return;
        }

        // Si la primera fila actual está vacía, reemplazarla
        if (count($this->editableQuotationItems) === 1
            && blank($this->editableQuotationItems[0]['part_code'])
            && blank($this->editableQuotationItems[0]['part_name'])) {
            $this->editableQuotationItems = [];
        }

        $defaultSupplierId = filled($this->selectedSupplierId ?? null)
            ? (int) $this->selectedSupplierId
            : ($this->selectedQuote()?->supplierRfqDocuments->first(fn ($d) => filled($d->supplier_id))?->supplier_id ?? '');

        foreach ($items as $item) {
            $this->editableQuotationItems[] = [
                'id' => null,
                'part_code' => $item['part_code'],
                'part_name' => $item['part_name'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'] ?: 'PZA',
                'part_brand' => $item['part_brand'] ?? '',
                'part_model' => $item['part_model'] ?? '',
                'part_serial_number' => $item['part_serial_number'] ?? '',
                'notes' => $item['notes'] ?? '',
                'product_id' => $item['product_id'] ?? null,
                'supplier_currency' => $item['supplier_currency'] ?? 'USD',
                'supplier_unit_cost' => $item['supplier_unit_cost'] ?? 0,
                'client_unit_price' => $item['client_unit_price'] ?? 0,
                'supplier_id' => $item['supplier_id'] ?? $defaultSupplierId,
            ];
        }

        $count = count($items);
        $this->closeBulkImportModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => "Se agregaron {$count} partida(s) desde la carga masiva."]);
    }

    public function saveQuotationItems(QuotationItemManagementService $service): void
    {
        $quote = $this->selectedQuote();
        if (! $quote) {
            return;
        }

        try {
            $editorStage = $this->stageKeyForStatus($quote->status);
            $itemsForCurrentStage = collect($this->editableQuotationItems)
                ->map(function (array $item) use ($editorStage): array {
                    if ($editorStage !== 'client_offer') {
                        unset($item['client_unit_price']);
                    }

                    if (! in_array($editorStage, ['client_offer', 'purchase_preparation', 'supplier_response', 'supplier_bid', 'supplier_selection'], true)) {
                        unset($item['supplier_currency'], $item['supplier_unit_cost']);
                    }

                    return $item;
                })
                ->all();

            $service->updateItems(
                $quote,
                $itemsForCurrentStage,
                $this->deletedQuotationItemIds,
                auth()->user(),
                $this->quotationItemChangeReason ?: null
            );

            $this->closeManageItemsModal();
            $this->loadSupplierRfqDocumentDefaults();

            // Sincronizar estado del proveedor y preliquidación con las partidas actualizadas
            $freshQuote = $this->selectedQuote();
            if ($freshQuote && filled($this->selectedSupplierId ?? null)) {
                $savedResponse = $freshQuote->supplierBidResponses
                    ->where('supplier_id', (int) $this->selectedSupplierId)
                    ->sortByDesc('id')->first();
                if ($savedResponse) {
                    $this->loadSavedSupplierResponse($savedResponse);
                } else {
                    $this->loadSupplierBidDefaults();
                }
            } elseif ($freshQuote) {
                $this->loadSupplierBidDefaults();
            }

            $this->dispatch('preliquidation-items-changed');
            $this->dispatch('notify', ['type' => 'success', 'message' => 'Partidas de la cotización actualizadas.']);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        }
    }

    private function blankQuotationItem(): array
    {
        $defaultSupplierId = filled($this->selectedSupplierId ?? null)
            ? (int) $this->selectedSupplierId
            : ($this->selectedQuote()?->supplierRfqDocuments->first(fn ($d) => filled($d->supplier_id))?->supplier_id ?? '');

        return [
            'id' => null,
            'part_code' => '',
            'part_name' => '',
            'quantity' => 1,
            'unit' => 'PZA',
            'part_brand' => '',
            'part_model' => '',
            'part_serial_number' => '',
            'notes' => '',
            'product_id' => null,
            'supplier_currency' => 'USD',
            'supplier_unit_cost' => '0',
            'client_unit_price' => '0',
            'supplier_id' => $defaultSupplierId,
        ];
    }
}
