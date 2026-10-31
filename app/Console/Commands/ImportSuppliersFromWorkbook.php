<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Entity;
use App\Models\MachineType;
use App\Models\PaymentMethod;
use App\Models\ProductType;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSuppliersFromWorkbook extends Command
{
    protected $signature = 'suppliers:import-workbook {path : Ruta absoluta del archivo XLSX}';
    protected $description = 'Importa proveedores, consolida duplicados y agrega marcas, calidades y categorías.';

    private const QUALITY_CODES = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");
            return self::FAILURE;
        }

        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, true);
        $suppliers = [];

        foreach (array_slice($rows, 2, null, true) as $row) {
            $name = trim((string) ($row['A'] ?? ''));
            if ($name === '') {
                continue;
            }

            $key = Str::lower(preg_replace('/\s+/', ' ', $name));
            $supplier = $suppliers[$key] ?? $this->blankSupplier($name);
            $supplier['brands'] = array_values(array_unique([...$supplier['brands'], ...$this->splitList($row['B'] ?? null)]));
            $supplier['qualities'] = array_values(array_unique([...$supplier['qualities'], ...$this->qualities($row)]));
            $supplier['machines'] = array_values(array_unique([...$supplier['machines'], ...$this->splitList($row['H'] ?? null)]));
            $supplier['products'] = array_values(array_unique([...$supplier['products'], ...$this->splitList($row['J'] ?? null)]));
            $supplier['country'] ??= $this->value($row['I'] ?? null);
            $supplier['credit'] = $supplier['credit'] || $this->yes($row['F'] ?? null);
            $supplier['representation'] = $supplier['representation'] || $this->yes($row['G'] ?? null);
            $warranty = trim(implode(' ', array_filter([(string) ($row['R'] ?? ''), (string) ($row['S'] ?? '')])));
            if ($warranty !== '') $supplier['warranties'] = array_values(array_unique([...$supplier['warranties'], $warranty]));
            $this->mergeContact($supplier, [$row['K'] ?? null, $row['L'] ?? null, $row['M'] ?? null]);
            $suppliers[$key] = $supplier;
        }

        foreach ($suppliers as $supplierData) {
            $supplier = Entity::updateOrCreate(
                ['type' => 'proveedor', 'name' => $supplierData['name']],
                [
                    'legal_name' => $supplierData['name'],
                    'country' => $supplierData['country'],
                    'email' => $supplierData['email'],
                    'website' => $supplierData['website'],
                    'phone_mobile' => $supplierData['phone'],
                    'contact_name' => $supplierData['contact'],
                    'quality_types' => $supplierData['qualities'],
                    'supplier_metadata' => ['representation' => $supplierData['representation'], 'warranties' => $supplierData['warranties']],
                    'status' => 'activo',
                ]
            );

            $supplier->brands()->syncWithoutDetaching($this->catalogIds(Brand::class, $supplierData['brands']));
            $supplier->machineTypes()->syncWithoutDetaching($this->catalogIds(MachineType::class, $supplierData['machines']));
            $supplier->productTypes()->syncWithoutDetaching($this->catalogIds(ProductType::class, $supplierData['products']));

            if ($supplierData['credit']) {
                $supplier->paymentMethods()->syncWithoutDetaching($this->catalogIds(PaymentMethod::class, ['Credito comercial']));
            }
        }

        $this->info('Importados '.count($suppliers).' proveedores únicos.');
        return self::SUCCESS;
    }

    private function blankSupplier(string $name): array
    {
        return ['name' => trim($name), 'brands' => [], 'qualities' => [], 'machines' => [], 'products' => [], 'warranties' => [], 'country' => null, 'email' => null, 'website' => null, 'phone' => null, 'contact' => null, 'credit' => false, 'representation' => false];
    }

    private function qualities(array $row): array
    {
        return collect([$row['C'] ?? null, $row['D'] ?? null, $row['E'] ?? null])
            ->flatMap(fn ($value) => preg_match_all('/\b(?:A1|A2|B1|B2|C1|C2)\b/i', (string) $value, $matches) ? $matches[0] : [])
            ->map(fn ($value) => strtoupper($value))
            ->filter(fn ($value) => in_array($value, self::QUALITY_CODES, true))
            ->unique()->values()->all();
    }

    private function mergeContact(array &$supplier, array $values): void
    {
        foreach ($values as $raw) {
            $value = $this->value($raw);
            if (! $value) continue;
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) { $supplier['email'] ??= $value; continue; }
            if (filter_var($value, FILTER_VALIDATE_URL)) { $supplier['website'] ??= $value; continue; }
            if (preg_match('/\d{5,}/', preg_replace('/\D+/', '', $value))) { $supplier['phone'] ??= $value; continue; }
            $supplier['contact'] ??= $value;
        }
    }

    private function catalogIds(string $model, array $names): array
    {
        return collect($names)->filter()->map(function ($name) use ($model) {
            $name = trim($name);
            return $model::firstOrCreate(['name' => $name], ['slug' => Str::slug($name) ?: Str::random(8), 'is_active' => true])->id;
        })->all();
    }

    private function splitList(mixed $value): array
    {
        return collect(preg_split('/\s*,\s*|\s+y\s+/iu', (string) $value) ?: [])
            ->map(fn ($item) => trim(rtrim($item, '.')))->filter()->values()->all();
    }

    private function value(mixed $value): ?string { $value = trim((string) $value); return $value !== '' ? $value : null; }
    private function yes(mixed $value): bool { return in_array(Str::lower(trim((string) $value)), ['si', 'sí', 'yes'], true); }
}
