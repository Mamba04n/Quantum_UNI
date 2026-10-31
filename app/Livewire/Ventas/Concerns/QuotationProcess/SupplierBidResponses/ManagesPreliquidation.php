<?php

namespace App\Livewire\Ventas\Concerns\QuotationProcess\SupplierBidResponses;

use App\Services\PreliquidationCalculator;

trait ManagesPreliquidation
{
    public array $preliquidation = [];
    #[\Livewire\Attributes\Locked]
    public ?string $preliquidationRevision = null;
    #[\Livewire\Attributes\Locked]
    public array $preliquidationLegacyCosts = [];
    public string $preliquidationPaste = '';
    public array $preliquidationPreview = [];
    public $preliquidationFile;
    public int $preliquidationAddCount = 1;
    public string $preliquidationChange = '';

    public function previewPreliquidationItems(): void
    {
        $this->preliquidationPreview = [];
        $this->validate(['preliquidationPaste' => 'required|string|max:1000000']);
        $this->preliquidationPreview = app(\App\Services\PreliquidationItemParser::class)->text($this->preliquidationPaste);
    }

    public function updatedPreliquidationFile(): void
    {
        $this->preliquidationPreview = [];
        $this->validate(['preliquidationFile' => 'required|file|mimes:xlsx,csv,txt|max:10240']);
        // A simple temporary name avoids Windows ZipArchive failures with Livewire filenames.
        $temporary = tempnam(sys_get_temp_dir(), 'preliq_');
        copy($this->preliquidationFile->getRealPath(), $temporary);
        $book = null;
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($temporary);
            $reader->setReadDataOnly(true);
            $sheets = $reader->listWorksheetInfo($temporary);
            if (($sheets[0]['totalRows'] ?? 0) > 1000 || ($sheets[0]['totalColumns'] ?? 0) > 100) {
                throw \Illuminate\Validation\ValidationException::withMessages(['preliquidationFile' => 'La primera hoja supera 1000 filas o 100 columnas. Importa únicamente las partidas (hasta 500).']);
            }
            $reader->setLoadSheetsOnly($sheets[0]['worksheetName']);
            $book = $reader->load($temporary);
            $this->preliquidationPreview = app(\App\Services\PreliquidationItemParser::class)->rows($book->getSheet(0)->toArray(null, false, false, false));
        } catch (\Illuminate\Validation\ValidationException $exception) { throw $exception;
        } catch (\Throwable $exception) {
            report($exception);
            throw \Illuminate\Validation\ValidationException::withMessages(['preliquidationFile' => 'No se pudo leer el archivo. Usa XLSX o CSV con las cinco columnas indicadas.']);
        } finally { $book?->disconnectWorksheets(); unlink($temporary); }
    }

    public function confirmPreliquidationImport(): void
    {
        if (count($this->preliquidationPreview) + count($this->supplierBidItems) + count($this->supplierBidExtraItems) > 500) {
            throw \Illuminate\Validation\ValidationException::withMessages(['preliquidation' => 'El proveedor admite hasta 500 partidas; no se importó ninguna fila.']);
        }
        $requestItems = $this->selectedQuote()?->items ?? collect();
        foreach ($this->preliquidationPreview as $row) {
            $matches = $requestItems->filter(fn ($item) => strcasecmp(trim((string)$item->part_number), trim((string)$row['quoted_part_number'])) === 0);
            if ($matches->count() === 1) $row['quotation_request_item_id'] = $matches->first()->id;
            $this->appendPreliquidationRow($row);
        }
        $this->preliquidationPreview = [];
        $this->preliquidationPaste = '';
        $this->preliquidationFile = null;
        $this->dispatch('preliquidation-items-changed');
    }

    public function addPreliquidationItems(): void
    {
        $this->validate(['preliquidationAddCount' => 'required|integer|min:1|max:100']);
        if ($this->preliquidationAddCount + count($this->supplierBidItems) + count($this->supplierBidExtraItems) > 500) {
            throw \Illuminate\Validation\ValidationException::withMessages(['preliquidation' => 'El proveedor admite hasta 500 partidas.']);
        }
        for ($i = 0; $i < $this->preliquidationAddCount; $i++) $this->appendPreliquidationRow([]);
        $this->dispatch('preliquidation-items-changed');
    }

    private function appendPreliquidationRow(array $row): void
    {
        if (count($this->supplierBidItems) + count($this->supplierBidExtraItems) >= 500) {
            throw \Illuminate\Validation\ValidationException::withMessages(['preliquidation' => 'Máximo 500 partidas por proveedor.']);
        }
        $key = 'new_'.\Illuminate\Support\Str::uuid();
        $this->supplierBidExtraItems[$key] = $row + ['quoted_part_number' => '', 'quoted_description' => '',
            'quoted_quantity' => 1, 'unit_cost' => 0, 'weight_lb' => 0, 'item_status' => 'quoted', 'is_standalone' => true,
            'quotation_request_item_id' => null, 'warranty' => '', 'delivery_time' => '', 'notes' => ''];
    }

    public function duplicatePreliquidationItem(string $key): void
    {
        $row = $this->supplierBidItems[$key] ?? $this->supplierBidExtraItems[$key] ?? null;
        if (!$row) return;
        unset($row['response_item_id']);
        if (ctype_digit($key)) $row['quotation_request_item_id'] = (int)$key;
        $this->appendPreliquidationRow($row);
        $this->dispatch('preliquidation-items-changed');
    }

    public function removePreliquidationItem(string $key): void
    {
        unset($this->supplierBidItems[$key], $this->supplierBidExtraItems[$key], $this->supplierItemProformas[$key]);
        $this->dispatch('preliquidation-items-changed');
    }

    public function updatingPreliquidation($value, $key): void
    {
        if ($key === 'taxed' && !$value && ($this->preliquidation['taxed'] ?? true)) {
            $this->preliquidation['tax_rates'] = array_intersect_key($this->preliquidation, array_flip(['iva', 'dai', 'isc']));
        }
        $labels = ['handling' => 'Manejo (USD/lb)', 'freight' => 'Freight (USD/lb)', 'customs' => 'Desaduanaje (USD)',
            'local_transport' => 'Transporte (USD)', 'margin' => 'Margen (%)', 'exchange' => 'Tipo de cambio',
            'commission' => 'Comisión (%)', 'financing' => 'Financiamiento (%)', 'iva' => 'IVA (%)', 'dai' => 'DAI (%)', 'isc' => 'ISC (%)'];
        $this->preliquidationChange = isset($labels[$key]) ? $labels[$key].': '.($this->preliquidation[$key] ?? '—').' → '.$value.'. Se recalcularon las partidas de este proveedor.' : 'Configuración del proveedor actualizada.';
    }

    public function updatedPreliquidation($value, $key): void
    {
        unset($this->preliquidationLegacyCosts[$key]);
        if ($key === 'taxed') {
            foreach (['iva' => 15, 'dai' => 5, 'isc' => 5] as $tax => $rate) {
                $this->preliquidation[$tax] = $value ? ($this->preliquidation['tax_rates'][$tax] ?? $rate) : 0;
            }
        }
    }

    public function updateCostTotal(string $field, mixed $totalUsd): void
    {
        if (!in_array($field, ['freight', 'handling'], true)) {
            return;
        }

        $total = max(0, (float) $totalUsd);
        if ($total <= 0) {
            $this->preliquidation[$field] = 0;
            unset($this->preliquidationLegacyCosts[$field]);
            $label = $field === 'freight' ? 'Freight cost' : 'Manejo';
            $this->preliquidationChange = "Tarifa de {$label} restablecida a 0 USD/lb.";
            return;
        }

        $totals = $this->preliquidationTotals();
        $weight = (float) ($totals['weight'] ?? 0);

        if ($weight > 0) {
            $rate = round($total / $weight, 8);
            $this->preliquidation[$field] = $rate;
            unset($this->preliquidationLegacyCosts[$field]);
            $label = $field === 'freight' ? 'Freight cost' : 'Manejo';
            $rateFormatted = number_format($rate, 4);
            $this->preliquidationChange = "Tarifa de {$label} calculada: {$rateFormatted} USD/lb a partir de USD " . number_format($total, 2) . " en total (peso total: {$weight} lb).";
        } else {
            $this->addError("preliquidation.{$field}", 'Para calcular la tarifa de ' . ($field === 'freight' ? 'Freight cost' : 'Manejo') . ' por libra a partir del total, primero ingresa el peso de las partidas.');
            $this->dispatch('notify', [
                'type' => 'warning',
                'message' => 'Para calcular la tarifa a partir del total en USD, primero ingresa el peso de las partidas.',
            ]);
        }
    }

    public function preliquidationTotals(): array
    {
        return app(PreliquidationCalculator::class)->calculate($this->preliquidation, $this->supplierBidItems + $this->supplierBidExtraItems);
    }

    private function validatePreliquidation(): void
    {
        if ($this->preliquidationLegacyCosts) {
            throw \Illuminate\Validation\ValidationException::withMessages(['preliquidation' => 'Esta versión anterior tiene gastos sin peso registrado. Define las tarifas Manejo / Freight por libra antes de guardar; los importes originales se muestran como referencia.']);
        }
        if (! ($this->preliquidation['taxed'] ?? true)) {
            foreach (['iva', 'dai', 'isc'] as $tax) {
                $this->preliquidation[$tax] = 0;
            }
        }
        $rules = ['preliquidation.transport' => 'required|in:air,sea', 'preliquidation.taxed' => 'required|boolean',
            'supplierCurrency' => 'required|in:USD',
            'preliquidation.margin' => 'required|numeric|min:0|max:99.99', 'preliquidation.exchange' => 'required|numeric|gt:0|max:1000000'];
        foreach (['handling', 'freight', 'customs', 'local_transport'] as $field) {
            $rules['preliquidation.'.$field] = 'required|numeric|min:0|max:999999999';
        }
        foreach (['commission', 'financing', 'iva', 'dai', 'isc'] as $field) $rules['preliquidation.'.$field] = 'required|numeric|min:0|max:100';
        foreach (['supplierBidItems', 'supplierBidExtraItems'] as $group) {
            foreach ($this->{$group} as $key => $row) {
                $rules[$group.'.'.$key.'.quoted_part_number'] = 'required|string|max:255';
                $rules[$group.'.'.$key.'.quoted_description'] = 'required|string|max:1000';
                $rules[$group.'.'.$key.'.item_status'] = 'required|in:quoted,alternative,not_quoted';
                foreach (['warranty', 'delivery_time'] as $field) $rules[$group.'.'.$key.'.'.$field] = 'nullable|string|max:255';
                $rules[$group.'.'.$key.'.notes'] = 'nullable|string|max:1000';
                $rules[$group.'.'.$key.'.unit_cost'] = 'required|numeric|min:0|max:999999999|decimal:0,2';
                $rules[$group.'.'.$key.'.quoted_quantity'] = 'required|numeric|gt:0|max:999999999|decimal:0,2';
                foreach (['assigned_usd', 'assigned_nio'] as $field) $rules[$group.'.'.$key.'.'.$field] = 'nullable|numeric|min:0|max:999999999';
                if (($row['item_status'] ?? '') !== 'not_quoted') {
                    $rules[$group.'.'.$key.'.weight_lb'] = 'required|numeric|min:0|max:999999999';
                }
            }
        }
        $this->validate($rules);
    }
}
