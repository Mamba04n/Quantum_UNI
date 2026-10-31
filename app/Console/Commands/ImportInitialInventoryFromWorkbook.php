<?php

namespace App\Console\Commands;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMove;
use App\Services\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportInitialInventoryFromWorkbook extends Command
{
    protected $signature = 'inventory:import-initial {path : Ruta absoluta del archivo XLSX} {--dry-run : Valida el archivo sin guardar cambios}';

    protected $description = 'Importa existencias iniciales y crea la entrada correspondiente en Kardex.';

    public function handle(InventoryService $inventory): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error("No se encontro el archivo: {$path}");

            return self::FAILURE;
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $created = 0;
        $updated = 0;
        $moved = 0;
        $skipped = 0;

        foreach (range(2, $sheet->getHighestRow()) as $row) {
            $partNumber = $this->cell($sheet, 'A', $row);
            $name = $this->cell($sheet, 'C', $row);
            $quantity = (int) $this->numeric($this->cell($sheet, 'D', $row));

            if ($partNumber === '' && $name === '') {
                continue;
            }

            if ($name === '' || $quantity <= 0) {
                $skipped++;
                $this->warn("Fila {$row} omitida: falta descripcion o cantidad valida.");

                continue;
            }

            $replacement = $this->cell($sheet, 'B', $row);
            $brand = $this->cell($sheet, 'G', $row);
            $cost = $this->numeric($this->cell($sheet, 'E', $row));
            $reference = 'Inventario inicial 28-07-2026 · fila '.$row.($partNumber !== '' ? " · {$partNumber}" : '');

            if ($this->option('dry-run')) {
                $this->line("Fila {$row}: {$partNumber} · {$name} · {$quantity} und.");
                continue;
            }

            DB::transaction(function () use ($partNumber, $name, $quantity, $replacement, $brand, $cost, $reference, $inventory, &$created, &$updated, &$moved) {
                $product = $partNumber !== '' ? Product::where('part_number', $partNumber)->first() : null;

                if ($product) {
                    $product->forceFill([
                        'name' => $name,
                        'brand' => $brand !== '' ? strtoupper($brand) : $product->brand,
                        'description' => $name,
                        'part_number_alt_1' => $replacement !== '' ? $replacement : $product->part_number_alt_1,
                        'active' => true,
                    ])->save();
                    $updated++;
                } else {
                    $product = Product::create([
                        'name' => $name,
                        'description' => $name,
                        'brand' => $brand !== '' ? strtoupper($brand) : null,
                        'part_number' => $partNumber !== '' ? $partNumber : null,
                        'part_number_alt_1' => $replacement !== '' ? $replacement : null,
                        'type' => 'pieza',
                        'piece_rotation_type' => 'alta_rotacion',
                        'technical_category' => 'mecanico',
                        'sales_price' => 0,
                        'cost' => 0,
                        'total_cost' => 0,
                        'stock_on_hand' => 0,
                        'active' => true,
                    ]);
                    $created++;
                }

                if (StockMove::where('reference', $reference)->where('source_module', 'inventory_initial_import')->exists()) {
                    return;
                }

                $inventory->recordMovement(
                    productId: $product->id,
                    type: StockMovementType::In,
                    quantity: $quantity,
                    movementDate: now()->toDateString(),
                    costIn: $cost,
                    reference: $reference,
                    sourceModule: 'inventory_initial_import',
                );
                $moved++;
            });
        }

        if ($this->option('dry-run')) {
            $this->info('Validacion completada. No se realizaron cambios.');

            return self::SUCCESS;
        }

        $this->info("Importacion terminada: {$created} productos creados, {$updated} actualizados, {$moved} entradas Kardex, {$skipped} filas omitidas.");

        return self::SUCCESS;
    }

    private function cell($sheet, string $column, int $row): string
    {
        return trim((string) $sheet->getCell("{$column}{$row}")->getFormattedValue());
    }

    private function numeric(string $value): float
    {
        $value = preg_replace('/[^0-9,.-]/', '', $value) ?? '';

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(',', '', $value);
        } else {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0;
    }
}
