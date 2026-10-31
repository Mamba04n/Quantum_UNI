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

class ImportTtmDirectoryFromWorkbook extends Command
{
    protected $signature = 'directory:import-ttm {path : Ruta absoluta del archivo XLSX} {--dry-run : Solo valida sin guardar}';
    protected $description = 'Importa proveedores, socios comerciales y clientes desde el directorio TTM.';

    private const QUALITY_CODES = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error("No se encontro el archivo: {$path}");
            return self::FAILURE;
        }

        $book = IOFactory::load($path);
        $counts = ['proveedor' => 0, 'socio' => 0, 'cliente' => 0];

        $this->importSuppliers($book->getSheetByName('Proveedores'), 'proveedor', $counts);
        $this->importSuppliers($book->getSheetByName('Partners'), 'socio', $counts);
        $this->importCustomers($book->getSheetByName('Clientes'), $counts);

        $mode = $this->option('dry-run') ? 'Validacion' : 'Importacion';
        $this->info("{$mode} terminada: {$counts['proveedor']} proveedores, {$counts['socio']} partners y {$counts['cliente']} clientes.");

        return self::SUCCESS;
    }

    private function importSuppliers($sheet, string $type, array &$counts): void
    {
        if (! $sheet) return;
        $records = [];
        $startRow = $type === 'proveedor' ? 4 : 3;

        for ($row = $startRow; $row <= $sheet->getHighestRow(); $row++) {
            $name = $this->cell($sheet, 'A', $row);
            if ($name === '') continue;
            $key = $this->key($name);
            $record = $records[$key] ?? $this->supplierBlank($name);

            if ($type === 'proveedor') {
                $record['brands'] = $this->merge($record['brands'], $this->list($this->cell($sheet, 'E', $row)));
                $record['machines'] = $this->merge($record['machines'], $this->list($this->cell($sheet, 'F', $row)));
                $record['country'] ??= $this->value($this->cell($sheet, 'G', $row));
                $record['products'] = $this->merge($record['products'], $this->list($this->cell($sheet, 'J', $row)));
                $record['qualities'] = $this->merge($record['qualities'], $this->qualities([$this->cell($sheet, 'B', $row), $this->cell($sheet, 'C', $row), $this->cell($sheet, 'P', $row)]));
                $record['verified'] = $record['verified'] || Str::lower($this->cell($sheet, 'D', $row)) === 'verificado';
                $this->mergeContact($record, [$this->cell($sheet, 'K', $row), $this->cell($sheet, 'L', $row), $this->cell($sheet, 'M', $row), $this->cell($sheet, 'N', $row), $this->cell($sheet, 'O', $row)]);
            } else {
                $record['brands'] = $this->merge($record['brands'], $this->list($this->cell($sheet, 'B', $row)));
                $record['machines'] = $this->merge($record['machines'], $this->list($this->cell($sheet, 'H', $row)));
                $record['country'] ??= $this->value($this->cell($sheet, 'I', $row));
                $record['products'] = $this->merge($record['products'], $this->list($this->cell($sheet, 'J', $row)));
                $record['qualities'] = $this->merge($record['qualities'], $this->qualities([$this->cell($sheet, 'C', $row), $this->cell($sheet, 'D', $row), $this->cell($sheet, 'E', $row)]));
                $record['credit'] = $record['credit'] || $this->yes($this->cell($sheet, 'F', $row));
                $record['representation'] = $record['representation'] || $this->yes($this->cell($sheet, 'G', $row));
                $this->mergeContact($record, [$this->cell($sheet, 'K', $row), $this->cell($sheet, 'L', $row), $this->cell($sheet, 'M', $row)]);
            }
            $records[$key] = $record;
        }

        foreach ($records as $data) {
            $counts[$type]++;
            if ($this->option('dry-run')) continue;

            $entity = Entity::firstOrNew(['type' => $type, 'name' => $data['name']]);
            $existingMeta = (array) $entity->supplier_metadata;
            $entity->fill(array_filter([
                'legal_name' => $data['name'], 'country' => $data['country'], 'email' => $data['email'], 'website' => $data['website'],
                'phone_mobile' => $data['phone'], 'contact_name' => $data['contact'], 'quality_types' => $data['qualities'], 'status' => 'activo',
                'supplier_metadata' => array_merge($existingMeta, ['representation' => $data['representation'], 'verified' => $data['verified'], 'source' => 'TTM Construccion Nicaragua']),
            ], fn ($value) => $value !== null));
            $entity->save();
            $entity->brands()->syncWithoutDetaching($this->catalogIds(Brand::class, $data['brands']));
            $entity->machineTypes()->syncWithoutDetaching($this->catalogIds(MachineType::class, $data['machines']));
            $entity->productTypes()->syncWithoutDetaching($this->catalogIds(ProductType::class, $data['products']));
            if ($data['credit']) $entity->paymentMethods()->syncWithoutDetaching($this->catalogIds(PaymentMethod::class, ['Credito comercial']));
        }
    }

    private function importCustomers($sheet, array &$counts): void
    {
        if (! $sheet) return;
        $records = [];
        for ($row = 3; $row <= $sheet->getHighestRow(); $row++) {
            $company = $this->cell($sheet, 'A', $row);
            if ($company === '') continue;
            $key = $this->key($company);
            $record = $records[$key] ?? ['name' => $company, 'contacts' => [], 'email' => null, 'phone' => null, 'country' => null, 'department' => null, 'address' => null, 'machinery' => [], 'sector' => null, 'notes' => []];
            $contact = trim($this->cell($sheet, 'B', $row).' '.($this->cell($sheet, 'E', $row) ? '('.$this->cell($sheet, 'E', $row).')' : ''));
            if ($contact !== '') $record['contacts'][] = $contact;
            $record['phone'] ??= $this->value($this->cell($sheet, 'C', $row));
            $record['email'] ??= $this->value($this->cell($sheet, 'D', $row));
            $record['country'] ??= $this->value($this->cell($sheet, 'I', $row)) ?: 'Nicaragua';
            $record['department'] ??= $this->value($this->cell($sheet, 'J', $row));
            $record['address'] ??= $this->value($this->cell($sheet, 'H', $row));
            $record['machinery'] = $this->merge($record['machinery'], $this->list($this->cell($sheet, 'G', $row)));
            $record['sector'] ??= $this->value($this->cell($sheet, 'P', $row));
            if ($this->cell($sheet, 'O', $row) !== '') $record['notes'][] = $this->cell($sheet, 'O', $row);
            $records[$key] = $record;
        }

        foreach ($records as $data) {
            $counts['cliente']++;
            if ($this->option('dry-run')) continue;
            $contactInfo = implode(' | ', array_unique(array_filter([...$data['contacts'], $data['sector'] ? 'Sector: '.$data['sector'] : null, ...$data['notes']])));
            Entity::updateOrCreate(['type' => 'cliente', 'name' => $data['name']], array_filter([
                'legal_name' => $data['name'], 'contact_info' => $contactInfo ?: null, 'email' => $data['email'], 'phone_mobile' => $data['phone'],
                'country' => $data['country'], 'department' => $data['department'], 'address' => $data['address'], 'exact_address' => $data['address'],
                'machinery' => implode(', ', $data['machinery']) ?: null, 'status' => 'active',
            ], fn ($value) => $value !== null));
        }
    }

    private function supplierBlank(string $name): array { return ['name' => trim($name), 'brands' => [], 'machines' => [], 'products' => [], 'qualities' => [], 'country' => null, 'email' => null, 'website' => null, 'phone' => null, 'contact' => null, 'credit' => false, 'representation' => false, 'verified' => false]; }
    private function mergeContact(array &$data, array $values): void { foreach ($values as $value) { $value = $this->value($value); if (! $value) continue; if (filter_var($value, FILTER_VALIDATE_EMAIL)) { $data['email'] ??= $value; } elseif (filter_var($value, FILTER_VALIDATE_URL)) { $data['website'] ??= $value; } elseif (preg_match('/\d{5,}/', preg_replace('/\D+/', '', $value))) { $data['phone'] ??= $value; } else { $data['contact'] ??= $value; } } }
    private function catalogIds(string $model, array $names): array
    {
        return collect($names)->map(function ($name) use ($model) {
            $name = trim(rtrim($name, '.'));
            $slug = Str::slug($name) ?: Str::random(8);

            return $model::where('slug', $slug)->orWhere('name', $name)->first()
                ?? $model::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        })->map->id->all();
    }
    private function qualities(array $values): array { return collect($values)->flatMap(fn ($v) => preg_match_all('/\b(?:A1|A2|B1|B2|C1|C2)\b/i', $v, $m) ? $m[0] : [])->map(fn ($v) => strtoupper($v))->filter(fn ($v) => in_array($v, self::QUALITY_CODES, true))->unique()->values()->all(); }
    private function list(string $value): array { return collect(preg_split('/[\r\n,;]+|\s+y\s+/iu', $value) ?: [])->map(fn ($v) => trim($v))->filter(fn ($v) => mb_strlen($v) <= 255)->unique()->values()->all(); }
    private function merge(array $left, array $right): array { return array_values(array_unique([...$left, ...$right])); }
    private function cell($sheet, string $column, int $row): string { return trim((string) $sheet->getCell("{$column}{$row}")->getFormattedValue()); }
    private function key(string $value): string { return Str::lower(preg_replace('/\s+/', ' ', trim($value))); }
    private function value(string $value): ?string { return $value !== '' ? $value : null; }
    private function yes(string $value): bool { return in_array(Str::lower($value), ['si', 'sí', 'yes'], true); }
}
