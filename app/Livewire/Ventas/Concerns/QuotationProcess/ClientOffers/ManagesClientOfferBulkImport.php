<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\ClientOffers;

use App\Models\QuotationRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

trait ManagesClientOfferBulkImport
{
    public array $clientOfferBulkPreview = [];
    public bool $showClientOfferBulkModal = false;

    public function updatedClientOfferBulkFile(): void
    {
        $this->importClientOfferSpreadsheet();
    }

    public function importClientOfferSpreadsheet(): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);
            return;
        }

        $this->validate([
            'clientOfferBulkFile' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [], ['clientOfferBulkFile' => 'archivo Excel de precios']);

        $quote = $this->selectedQuote();
        if (! $quote || ! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya no se puede modificar en su estado actual.']);
            return;
        }

        try {
            $sheet = \App\Support\SafeSpreadsheetLoader::load($this->clientOfferBulkFile->getRealPath())->getActiveSheet();
            $headers = array_map(fn ($value) => strtoupper(trim((string) $value)), $sheet->rangeToArray('A4:H4')[0]);
            $expected = ['ITEM_ID', 'PRODUCTO', 'PROVEEDOR', 'COSTO_NIO', 'CANTIDAD', 'PRECIO_CLIENTE_NIO', 'GARANTIA', '50_PLUS'];
            if ($headers !== $expected) {
                throw ValidationException::withMessages(['clientOfferBulkFile' => 'El archivo no corresponde a la plantilla de precios de Oferta Cliente.']);
            }

            $rows = [];
            for ($row = 5; $row <= $sheet->getHighestDataRow(); $row++) {
                $itemId = (int) $sheet->getCell("A{$row}")->getValue();
                if ($itemId <= 0) {
                    continue;
                }

                $price = $sheet->getCell("F{$row}")->getCalculatedValue();
                $warranty = trim((string) $sheet->getCell("G{$row}")->getValue());
                $plus = strtoupper(trim((string) $sheet->getCell("H{$row}")->getValue()));
                if (! is_numeric($price) || (float) $price < 0) {
                    throw ValidationException::withMessages(['clientOfferBulkFile' => "La fila {$row} tiene un precio cliente inválido."]);
                }
                if (! in_array($warranty, ['Ninguna', 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'], true)) {
                    throw ValidationException::withMessages(['clientOfferBulkFile' => "La fila {$row} tiene una garantía inválida."]);
                }
                if (! in_array($plus, ['SI', 'SÍ', 'NO'], true)) {
                    throw ValidationException::withMessages(['clientOfferBulkFile' => "La fila {$row} debe indicar SI o NO en 50_PLUS."]);
                }
                $rows[$itemId] = [
                    'item_id' => $itemId,
                    'product' => trim((string) $sheet->getCell("B{$row}")->getValue()),
                    'supplier' => trim((string) $sheet->getCell("C{$row}")->getValue()),
                    'supplier_cost_nio' => round((float) $sheet->getCell("D{$row}")->getCalculatedValue(), 2),
                    'quantity' => (float) $sheet->getCell("E{$row}")->getCalculatedValue(),
                    'unit_price' => round((float) $price, 2),
                    'warranty_type' => $warranty === 'Ninguna' ? null : $warranty,
                    'has_warranty_extension' => in_array($plus, ['SI', 'SÍ'], true),
                ];
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['clientOfferBulkFile' => 'El archivo no contiene productos para actualizar.']);
            }

            $quoteItemIds = $quote->items->pluck('id')->map(fn ($id) => (int) $id);
            if (collect(array_keys($rows))->contains(fn ($id) => ! $quoteItemIds->contains((int) $id))) {
                throw ValidationException::withMessages(['clientOfferBulkFile' => 'El archivo contiene productos que no pertenecen a esta oferta.']);
            }

            $this->clientOfferBulkPreview = array_values($rows);
            $this->showClientOfferBulkModal = true;
            $this->resetValidation('clientOfferBulkFile');
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudo leer el Excel. Descarga una plantilla nueva e inténtalo nuevamente.']);
        }
    }

    public function applyClientOfferBulkImport(): void
    {
        if (! $this->canManageClientOfferEdits()) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No tienes permiso para editar la oferta en su estado actual.']);
            return;
        }

        $quote = $this->selectedQuote();
        if (! $quote || ! $this->canEditClientOfferData($quote)) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'La oferta ya no se puede modificar en su estado actual.']);
            $this->closeClientOfferBulkImport();
            return;
        }

        try {
            $rows = collect($this->clientOfferBulkPreview)
                ->mapWithKeys(function (array $row): array {
                    $itemId = (int) ($row['item_id'] ?? 0);
                    $price = $row['unit_price'] ?? null;
                    $warranty = $row['warranty_type'] ?? null;

                    if ($itemId <= 0 || ! is_numeric($price) || (float) $price < 0) {
                        throw ValidationException::withMessages(['clientOfferBulkFile' => 'La vista previa contiene datos inválidos. Vuelve a seleccionar el archivo.']);
                    }
                    if (! in_array($warranty, [null, 'A1', 'A2', 'B1', 'B2', 'C1', 'C2'], true)) {
                        throw ValidationException::withMessages(['clientOfferBulkFile' => 'La vista previa contiene una garantía inválida.']);
                    }

                    return [$itemId => [
                        'unit_price' => round((float) $price, 2),
                        'warranty_type' => $warranty,
                        'has_warranty_extension' => (bool) ($row['has_warranty_extension'] ?? false),
                    ]];
                })->all();

            if ($rows === []) {
                throw ValidationException::withMessages(['clientOfferBulkFile' => 'La vista previa ya no está disponible. Vuelve a seleccionar el archivo Excel.']);
            }

            $quote = $this->prepareClientOfferForEditing($quote);
            DB::transaction(function () use ($quote, $rows): void {
                $items = QuotationRequestItem::query()
                    ->where('quotation_request_id', $quote->id)
                    ->whereIn('id', array_keys($rows))
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($items->count() !== count($rows)) {
                    throw ValidationException::withMessages(['clientOfferBulkFile' => 'El archivo contiene productos que no pertenecen a esta oferta.']);
                }

                foreach ($rows as $itemId => $values) {
                    $item = $items->get($itemId);
                    $item->forceFill($values + [
                        'line_total' => round((float) $item->quantity * $values['unit_price'], 2),
                        'version' => ((int) $item->version) + 1,
                    ])->save();
                }
            });

            $this->recalculateClientOfferTotals($quote);
            $count = count($rows);
            $this->closeClientOfferBulkImport();
            $this->dispatch('notify', ['type' => 'success', 'message' => "Se actualizaron {$count} productos de la Oferta Cliente."]);
        } catch (ValidationException $e) {
            $this->dispatch('notify', ['type' => 'error', 'message' => collect($e->errors())->flatten()->first()]);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', ['type' => 'error', 'message' => 'No se pudieron aplicar los precios del Excel. Inténtalo nuevamente.']);
        }
    }

    public function closeClientOfferBulkImport(): void
    {
        $this->showClientOfferBulkModal = false;
        $this->clientOfferBulkFile = null;
        $this->clientOfferBulkPreview = [];
        $this->resetErrorBag('clientOfferBulkFile');
    }
}
