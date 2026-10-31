<?php

namespace App\Domain\Ventas\Cotizaciones\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class BulkItemParserService
{
    /**
     * Parsea un archivo subido (Excel .xlsx, .xls, .csv o .txt).
     *
     * @param  UploadedFile|object|string  $file
     * @return array<int, array{
     *     part_code: string,
     *     part_name: string,
     *     quantity: int,
     *     unit: string,
     *     part_brand: string,
     *     part_model: string,
     *     part_serial_number: string,
     *     notes: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     product_sku: string|null,
     *     product_stock: int,
     *     is_in_inventory: bool
     * }>
     */
    public function parseUploadedFile($file): array
    {
        $path = is_string($file) ? $file : $file->getRealPath();
        $originalName = is_string($file) ? $file : (method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : $file->getFilename());
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (in_array($ext, ['xlsx', 'xls'], true)) {
            return $this->parseExcelFile($path);
        }

        // CSV o TXT
        $content = file_get_contents($path);

        return $this->parse($content ?: '');
    }

    /**
     * Parsea una hoja de cálculo Excel (.xlsx / .xls).
     *
     * @return array<int, array{
     *     part_code: string,
     *     part_name: string,
     *     quantity: int,
     *     unit: string,
     *     part_brand: string,
     *     part_model: string,
     *     part_serial_number: string,
     *     notes: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     product_sku: string|null,
     *     product_stock: int,
     *     is_in_inventory: bool
     * }>
     */
    /**
     * Parsea una hoja de cálculo Excel (.xlsx / .xls).
     *
     * @return array<int, array{
     *     part_code: string,
     *     part_name: string,
     *     quantity: int,
     *     unit: string,
     *     part_brand: string,
     *     part_model: string,
     *     part_serial_number: string,
     *     notes: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     product_sku: string|null,
     *     product_stock: int,
     *     is_in_inventory: bool
     * }>
     */
    public function parseExcelFile(string $filePath): array
    {
        try {
            $spreadsheet = \App\Support\SafeSpreadsheetLoader::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($rows)) {
            return [];
        }

        // 1. Buscar la fila de encabezados / indicadores de columnas
        $headerRowIndex = null;
        $headerMap = null;

        foreach ($rows as $rowIndex => $row) {
            $columns = array_map(fn ($val) => trim((string) ($val ?? '')), (array) $row);
            if (empty(array_filter($columns, fn ($v) => $v !== ''))) {
                continue;
            }

            $detectedMap = $this->buildHeaderMap($columns);
            if ($detectedMap !== null) {
                $headerRowIndex = $rowIndex;
                $headerMap = $detectedMap;
                break;
            }
        }

        $items = [];
        $candidateRows = $headerRowIndex !== null ? array_slice($rows, $headerRowIndex + 1) : $rows;

        foreach ($candidateRows as $row) {
            $columns = array_map(fn ($val) => trim((string) ($val ?? '')), (array) $row);

            // Si toda la fila está vacía, continuar
            if (empty(array_filter($columns, fn ($v) => $v !== ''))) {
                continue;
            }

            // Ignorar filas de encabezado repetidas
            if ($this->isHeaderLine(implode(' ', $columns))) {
                continue;
            }

            // Ignorar fila de ejemplo / muestra (ej. fila 2 debajo del encabezado)
            if ($this->isExampleRow($columns)) {
                continue;
            }

            if ($headerMap !== null) {
                $item = $this->mapColumnsUsingHeaderMap($columns, $headerMap);
            } else {
                $item = $this->mapColumnsToItem(array_values($columns));
            }

            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Parsea texto libre, TSV copiado de Excel, CSV o texto separado por pipes/comas/tabulaciones.
     *
     * @return array<int, array{
     *     part_code: string,
     *     part_name: string,
     *     quantity: int,
     *     unit: string,
     *     part_brand: string,
     *     part_model: string,
     *     part_serial_number: string,
     *     notes: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     product_sku: string|null,
     *     product_stock: int,
     *     is_in_inventory: bool
     * }>
     */
    public function parse(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        if (empty($lines)) {
            return [];
        }

        // Detectar si alguna de las primeras líneas es encabezado
        $headerRowIndex = null;
        $headerMap = null;
        $splitLines = [];

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $columns = $this->splitColumns($trimmed);
            $splitLines[$index] = $columns;

            if ($headerMap === null) {
                $detectedMap = $this->buildHeaderMap($columns);
                if ($detectedMap !== null) {
                    $headerRowIndex = $index;
                    $headerMap = $detectedMap;
                }
            }
        }

        $items = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Si es la fila de encabezados, saltar
            if ($headerRowIndex !== null && $index === $headerRowIndex) {
                continue;
            }

            $columns = $splitLines[$index] ?? $this->splitColumns($line);
            if (empty($columns) || empty(array_filter($columns, fn ($v) => $v !== ''))) {
                continue;
            }

            // Ignorar encabezados comunes si se repiten
            if ($this->isHeaderLine($line)) {
                continue;
            }

            // Ignorar fila de ejemplo si se copia de la plantilla
            if ($this->isExampleRow($columns)) {
                continue;
            }

            if ($headerMap !== null) {
                $item = $this->mapColumnsUsingHeaderMap($columns, $headerMap);
            } else {
                $item = $this->mapColumnsToItem($columns);
            }

            if ($item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Construye un mapa de índices de columnas a partir de la fila de encabezados.
     *
     * @param  list<string>  $columns
     * @return array{
     *     code: int|null,
     *     name: int|null,
     *     quantity: int|null,
     *     unit: int|null,
     *     brand: int|null,
     *     model: int|null,
     *     serial: int|null,
     *     notes: int|null
     * }|null
     */
    private function buildHeaderMap(array $columns): ?array
    {
        $map = [
            'code' => null,
            'name' => null,
            'quantity' => null,
            'unit' => null,
            'brand' => null,
            'model' => null,
            'serial' => null,
            'notes' => null,
            'price' => null,
        ];

        $matchedCount = 0;

        foreach ($columns as $index => $col) {
            $normalized = $this->normalizeHeaderToken($col);
            if ($normalized === '') {
                continue;
            }

            // Código / ID / Referencia / Parte
            if ($map['code'] === null && $this->isCodeHeader($normalized)) {
                $map['code'] = $index;
                $matchedCount++;
                continue;
            }

            // Concepto / Descripción / Nombre / Producto
            if ($map['name'] === null && $this->isNameHeader($normalized)) {
                $map['name'] = $index;
                $matchedCount++;
                continue;
            }

            // Cantidad / Qty
            if ($map['quantity'] === null && $this->isQuantityHeader($normalized)) {
                $map['quantity'] = $index;
                $matchedCount++;
                continue;
            }

            // Unidad / Unit / UOM
            if ($map['unit'] === null && $this->isUnitHeader($normalized)) {
                $map['unit'] = $index;
                $matchedCount++;
                continue;
            }

            // Marca / Brand
            if ($map['brand'] === null && $this->isBrandHeader($normalized)) {
                $map['brand'] = $index;
                $matchedCount++;
                continue;
            }

            // Modelo / Model
            if ($map['model'] === null && $this->isModelHeader($normalized)) {
                $map['model'] = $index;
                $matchedCount++;
                continue;
            }

            // Serie / Serial
            if ($map['serial'] === null && $this->isSerialHeader($normalized)) {
                $map['serial'] = $index;
                $matchedCount++;
                continue;
            }

            // Notas / Observaciones
            if ($map['notes'] === null && $this->isNotesHeader($normalized)) {
                $map['notes'] = $index;
                $matchedCount++;
                continue;
            }

            // Precio / Costo / Unit Price
            if ($map['price'] === null && $this->isPriceHeader($normalized)) {
                $map['price'] = $index;
                $matchedCount++;
                continue;
            }
        }

        // Se considera fila de encabezados si coincide con al menos 2 columnas clave
        // o si coincide con cantidad y concepto/id
        if ($matchedCount >= 2 || ($map['quantity'] !== null && ($map['name'] !== null || $map['code'] !== null))) {
            return $map;
        }

        return null;
    }

    private function normalizeHeaderToken(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = str_replace(['_', '-', '.', '/', '#', '*', ':', '(', ')'], ' ', $h);
        $h = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $h);

        return trim(preg_replace('/\s+/', ' ', $h) ?? '');
    }

    private function isCodeHeader(string $h): bool
    {
        return in_array($h, [
            'codigo', 'id', 'codigo parte', 'codigo de parte', 'part code', 'part number', 'n p', 'np',
            'no parte', 'num parte', 'numero parte', 'numero de parte', 'referencia', 'ref', 'sku', 'cve', 'clave',
            'item id', 'codigo solicitado', 'codigo cotizado',
        ], true);
    }

    private function isNameHeader(string $h): bool
    {
        return in_array($h, [
            'concepto', 'descripcion', 'descripcion parte', 'descripcion de parte', 'nombre', 'nombre parte',
            'producto', 'item name', 'part name', 'description', 'detalle', 'articulo', 'repuesto', 'pieza',
            'descripcion solicitada', 'descripcion cotizada',
        ], true);
    }

    private function isQuantityHeader(string $h): bool
    {
        return in_array($h, [
            'cantidad', 'cant', 'qty', 'quantity', 'unidades', 'cant solicitada', 'cantidad solicitada',
            'cantidad cotizada', 'cuanto', 'pzas', 'piezas', 'total piezas',
        ], true);
    }

    private function isUnitHeader(string $h): bool
    {
        return in_array($h, ['unidad', 'unit', 'uom', 'medida', 'ud', 'und', 'unid'], true);
    }

    private function isBrandHeader(string $h): bool
    {
        return in_array($h, ['marca', 'brand', 'fabricante', 'make'], true);
    }

    private function isModelHeader(string $h): bool
    {
        return in_array($h, ['modelo', 'model'], true);
    }

    private function isSerialHeader(string $h): bool
    {
        return in_array($h, [
            'serie', 'serial', 'numero serie', 'numero de serie', 'serial number', 'n serie', 'num serie', 'sn', 's n',
        ], true);
    }

    private function isNotesHeader(string $h): bool
    {
        return in_array($h, [
            'notas', 'nota', 'observaciones', 'observacion', 'comentarios', 'comentario', 'notes', 'comments', 'obs',
        ], true);
    }

    private function isPriceHeader(string $h): bool
    {
        return in_array($h, [
            'precio unit', 'precio unitario', 'precio unit.', 'costo unit', 'costo unitario',
            'costo unit.', 'costo', 'cost', 'precio', 'price', 'unit price', 'unitprice',
            'unit cost', 'unitcost', 'p unit', 'p unitario', 'p. unit', 'p.unit', 'costo proveedor',
        ], true);
    }

    /**
     * Mapea una fila de columnas utilizando el mapa de encabezados detectado dinámicamente.
     *
     * @param  list<string>  $columns
     * @param  array<string, int|null>  $map
     * @return array{
     *     part_code: string,
     *     part_name: string,
     *     quantity: int,
     *     unit: string,
     *     part_brand: string,
     *     part_model: string,
     *     part_serial_number: string,
     *     notes: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     product_sku: string|null,
     *     product_stock: int,
     *     is_in_inventory: bool
     * }|null
     */
    private function mapColumnsUsingHeaderMap(array $columns, array $map): ?array
    {
        $partCode = $map['code'] !== null ? trim((string) ($columns[$map['code']] ?? '')) : '';
        $partName = $map['name'] !== null ? trim((string) ($columns[$map['name']] ?? '')) : '';

        $rawQty = $map['quantity'] !== null ? trim((string) ($columns[$map['quantity']] ?? '')) : '';
        $quantity = is_numeric(str_replace(',', '', $rawQty)) ? max(1, (int) round((float) str_replace(',', '', $rawQty))) : 1;

        $unit = $map['unit'] !== null ? trim((string) ($columns[$map['unit']] ?? '')) : 'PZA';
        $brand = $map['brand'] !== null ? trim((string) ($columns[$map['brand']] ?? '')) : '';
        $model = $map['model'] !== null ? trim((string) ($columns[$map['model']] ?? '')) : '';
        $serial = $map['serial'] !== null ? trim((string) ($columns[$map['serial']] ?? '')) : '';
        $notes = $map['notes'] !== null ? trim((string) ($columns[$map['notes']] ?? '')) : '';

        $rawPrice = $map['price'] !== null ? trim((string) ($columns[$map['price']] ?? '')) : '';
        $rawPrice = str_replace(['USD', '$', 'C$', 'NIO', ' ', "\xc2\xa0"], '', $rawPrice);
        if (str_contains($rawPrice, ',') && str_contains($rawPrice, '.')) {
            $rawPrice = strrpos($rawPrice, ',') > strrpos($rawPrice, '.')
                ? str_replace(',', '.', str_replace('.', '', $rawPrice))
                : str_replace(',', '', $rawPrice);
        } elseif (str_contains($rawPrice, ',')) {
            $rawPrice = preg_match('/^\d{1,3}(,\d{3})+$/', $rawPrice) ? str_replace(',', '', $rawPrice) : str_replace(',', '.', $rawPrice);
        }
        $price = is_numeric($rawPrice) ? max(0, round((float) $rawPrice, 2)) : 0.0;

        if (blank($partCode) && blank($partName)) {
            return null;
        }

        if (blank($partCode)) {
            $partCode = $partName;
        }
        if (blank($partName)) {
            $partName = $partCode;
        }

        return $this->formatItemPayload($partCode, $partName, $quantity, $unit, $brand, $model, $serial, $notes, $price);
    }

    /**
     * @return list<string>
     */
    private function splitColumns(string $line): array
    {
        if (str_contains($line, "\t")) {
            return array_map('trim', explode("\t", $line));
        }

        if (str_contains($line, '|')) {
            return array_map('trim', explode('|', $line));
        }

        if (str_contains($line, ';')) {
            return array_map('trim', str_getcsv($line, ';'));
        }

        if (str_contains($line, ',')) {
            return array_map('trim', str_getcsv($line, ','));
        }

        // Si es espacio múltiple (2 o más espacios)
        if (preg_match('/\s{2,}/', $line)) {
            return array_map('trim', preg_split('/\s{2,}/', $line) ?: []);
        }

        // Si es una sola palabra o texto
        return [$line];
    }

    /**
     * @param  list<string>  $columns
     * @return array{
     *     part_code: string,
     *     part_name: string,
     *     quantity: int,
     *     unit: string,
     *     part_brand: string,
     *     part_model: string,
     *     part_serial_number: string,
     *     notes: string,
     *     product_id: int|null,
     *     product_name: string|null,
     *     product_sku: string|null,
     *     product_stock: int,
     *     is_in_inventory: bool
     * }|null
     */
    private function mapColumnsToItem(array $columns): ?array
    {
        $count = count($columns);
        if ($count === 0) {
            return null;
        }

        $partCode = '';
        $partName = '';
        $quantity = 1;
        $unit = 'PZA';
        $brand = '';
        $model = '';
        $serial = '';
        $notes = '';

        if ($count === 1) {
            // Solo una columna: se asume que es el código o descripción
            $partCode = $columns[0];
            $partName = $columns[0];
        } elseif ($count === 2) {
            // 2 columnas: Código | Descripción  O  Descripción | Cantidad
            if (is_numeric($columns[1])) {
                $partName = $columns[0];
                $partCode = $columns[0];
                $quantity = max(1, (int) round((float) $columns[1]));
            } else {
                $partCode = $columns[0];
                $partName = $columns[1];
            }
        } elseif ($count === 3) {
            // 3 columnas: Código | Descripción | Cantidad
            $partCode = $columns[0];
            $partName = $columns[1];
            $quantity = is_numeric($columns[2]) ? max(1, (int) round((float) $columns[2])) : 1;
            if (! is_numeric($columns[2]) && filled($columns[2])) {
                $unit = $columns[2];
            }
        } elseif ($count === 4) {
            // 4 columnas: Código | Descripción | Cantidad | Unidad
            $partCode = $columns[0];
            $partName = $columns[1];
            $quantity = is_numeric($columns[2]) ? max(1, (int) round((float) $columns[2])) : 1;
            $unit = filled($columns[3]) ? $columns[3] : 'PZA';
        } elseif ($count === 5) {
            // 5 columnas: Código | Descripción | Cantidad | Unidad | Marca
            $partCode = $columns[0];
            $partName = $columns[1];
            $quantity = is_numeric($columns[2]) ? max(1, (int) round((float) $columns[2])) : 1;
            $unit = filled($columns[3]) ? $columns[3] : 'PZA';
            $brand = $columns[4];
        } else {
            // 6+ columnas: Código | Descripción | Cantidad | Unidad | Marca | Modelo | Serie | Notas
            $partCode = $columns[0];
            $partName = $columns[1];
            $quantity = is_numeric($columns[2]) ? max(1, (int) round((float) $columns[2])) : 1;
            $unit = filled($columns[3]) ? $columns[3] : 'PZA';
            $brand = $columns[4] ?? '';
            $model = $columns[5] ?? '';
            $serial = $columns[6] ?? '';
            $notes = $columns[7] ?? '';
        }

        if (blank($partCode) && blank($partName)) {
            return null;
        }

        if (blank($partCode)) {
            $partCode = $partName;
        }
        if (blank($partName)) {
            $partName = $partCode;
        }

        return $this->formatItemPayload($partCode, $partName, $quantity, $unit, $brand, $model, $serial, $notes, 0.0);
    }

    private function formatItemPayload(
        string $partCode,
        string $partName,
        int $quantity,
        string $unit,
        string $brand,
        string $model,
        string $serial,
        string $notes,
        float $supplierUnitCost = 0.0
    ): array {
        $partCode = trim($partCode);
        $partName = trim($partName);
        $brand = trim($brand);
        $model = trim($model);
        $serial = trim($serial);
        $notes = trim($notes);
        $unit = trim($unit) ?: 'PZA';

        // Validación y cruce automático con el catálogo / inventario existente
        $product = $this->findMatchingProduct($partCode, $partName);

        if ($product) {
            $productId = $product->id;
            $productName = $product->name;
            $productSku = $product->sku;
            $productStock = (int) ($product->stock_on_hand ?? 0);
            $isInInventory = true;

            // Autocompletar campos vacíos con datos del producto existente
            if (blank($brand) && filled($product->brand)) {
                $brand = (string) $product->brand;
            }
            if (blank($model) && filled($product->model)) {
                $model = (string) $product->model;
            }
            if ($unit === 'PZA' && filled($product->unit)) {
                $unit = (string) $product->unit;
            }
        } else {
            $productId = null;
            $productName = null;
            $productSku = null;
            $productStock = 0;
            $isInInventory = false;
        }

        return [
            'part_code' => $partCode,
            'part_name' => $partName,
            'quantity' => $quantity,
            'unit' => $unit,
            'part_brand' => $brand,
            'part_model' => $model,
            'part_serial_number' => $serial,
            'notes' => $notes,
            'supplier_unit_cost' => $supplierUnitCost,
            'product_id' => $productId,
            'product_name' => $productName,
            'product_sku' => $productSku,
            'product_stock' => $productStock,
            'is_in_inventory' => $isInInventory,
        ];
    }

    /**
     * Busca si el código o nombre coincide con algún producto existente en el catálogo.
     */
    private function findMatchingProduct(string $partCode, string $partName): ?Product
    {
        if (filled($partCode)) {
            $matched = Product::query()
                ->where('sku', $partCode)
                ->orWhere('part_number', $partCode)
                ->first();

            if ($matched) {
                return $matched;
            }
        }

        if (filled($partName)) {
            $matchedByName = Product::query()
                ->where('name', $partName)
                ->first();

            if ($matchedByName) {
                return $matchedByName;
            }
        }

        return null;
    }

    private function isHeaderLine(string $line): bool
    {
        $normalized = mb_strtolower($line);
        $headerTokens = ['part number', 'código', 'codigo', 'item', 'descripcion', 'descripción', 'cantidad', 'cant', 'qty', 'unidad', 'marca', 'modelo', 'concepto', 'producto'];

        $matchCount = 0;
        foreach ($headerTokens as $token) {
            if (str_contains($normalized, $token)) {
                $matchCount++;
            }
        }

        return $matchCount >= 2;
    }

    /**
     * Detecta si la fila es una fila de muestra o ejemplo de la plantilla.
     *
     * @param  list<string>  $columns
     */
    private function isExampleRow(array $columns): bool
    {
        $lineJoined = mb_strtolower(implode(' ', array_filter($columns, fn ($v) => is_string($v) || is_numeric($v))));

        $exampleKeywords = [
            'ejemplo',
            '[ejemplo]',
            'ejemplo-',
            'ej:',
            'sample',
            'muestra',
            'fila de muestra',
            'reemplazar esta fila',
            'borrar o reemplazar',
            'de muestra',
            'ejem',
            'sample-',
            'ej-',
        ];

        foreach ($exampleKeywords as $kw) {
            if (str_contains($lineJoined, $kw)) {
                return true;
            }
        }

        return false;
    }
}
