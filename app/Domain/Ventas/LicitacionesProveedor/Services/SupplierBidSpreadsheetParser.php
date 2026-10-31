<?php

namespace App\Domain\Ventas\LicitacionesProveedor\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;

class SupplierBidSpreadsheetParser
{
    public function parse(string $path): array
    {
        return $this->parseWorkbook($path)['rows'];
    }

    public function parseWorkbook(string $path): array
    {
        $spreadsheet = \App\Support\SafeSpreadsheetLoader::load($path);
        $allRows = $spreadsheet->getSheetByName('Respuesta Proveedor')?->toArray(null, true, true, false)
            ?? $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (count($allRows) < 2) {
            return ['metadata' => [], 'rows' => []];
        }

        $headerRowIndex = null;
        $headers = [];
        foreach ($allRows as $rowIndex => $row) {
            $normalizedRow = array_map(fn ($value) => $this->normalize((string) $value), $row);
            if (in_array('ITEM_ID', $normalizedRow, true) || in_array('ID', $normalizedRow, true) || (in_array('CODIGO_COTIZADO', $normalizedRow, true) && in_array('PRECIO_UNITARIO', $normalizedRow, true))) {
                $headerRowIndex = $rowIndex;
                // Standardize ID to ITEM_ID if needed
                $headers = array_map(fn ($h) => in_array($h, ['ID', 'ITEM'], true) ? 'ITEM_ID' : $h, $normalizedRow);
                break;
            }
        }

        if ($headerRowIndex === null) {
            return ['metadata' => [], 'rows' => []];
        }

        $indexes = array_flip($headers);
        $rows = array_slice($allRows, $headerRowIndex + 1);

        $value = static fn (array $row, string $key) => trim((string) ($row[$indexes[$key] ?? -1] ?? ''));
        $parsed = [];
        foreach ($rows as $row) {
            // Check if example row
            $joined = mb_strtolower(implode(' ', array_map(fn ($v) => (string) $v, $row)));
            if (str_contains($joined, 'ejemplo') || str_contains($joined, 'sample') || str_contains($joined, 'muestra') || str_contains($joined, 'reemplazar esta fila')) {
                continue;
            }

            $itemId = (int) $value($row, 'ITEM_ID');
            if ($itemId <= 0) {
                continue;
            }
            $status = $this->status($value($row, 'ESTADO'));
            $parsed[] = [
                'item_id' => $itemId,
                'item_status' => $status,
                'not_quoted' => $status === 'not_quoted',
                'quoted_part_number' => $value($row, 'CODIGO_COTIZADO'),
                'quoted_description' => $value($row, 'DESCRIPCION_COTIZADA'),
                'quoted_quantity' => $value($row, 'CANTIDAD_COTIZADA'),
                'unit_cost' => $value($row, 'PRECIO_UNITARIO'),
                'currency' => strtoupper($value($row, 'MONEDA')),
                'delivery_time' => $value($row, 'TIEMPO_ENTREGA'),
                'estimated_arrival_date' => $value($row, 'FECHA_ESTIMADA'),
                'brand' => $value($row, 'MARCA'),
                'part_model' => $value($row, 'MODELO'),
                'serial_number' => $value($row, 'NUMERO_SERIE'),
                'warranty' => $value($row, 'GARANTIA'),
                'notes' => $value($row, 'NOTAS'),
                'payment_terms' => $value($row, 'CONDICIONES_PAGO'),
                'valid_until' => $value($row, 'VALIDEZ_HASTA'),
            ];
        }

        $metadata = [];
        $control = $spreadsheet->getSheetByName('Control');
        if ($control) {
            foreach ($control->toArray(null, true, true, false) as $row) {
                $key = $this->normalize((string) ($row[0] ?? ''));
                if ($key !== '' && $key !== 'CAMPO') {
                    $metadata[$key] = trim((string) ($row[1] ?? ''));
                }
            }
        }

        return ['metadata' => $metadata, 'rows' => $parsed];
    }

    private function normalize(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = str_replace(['*', '(', ')', '#', '.'], '', $value);
        return str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', ' '], ['A', 'E', 'I', 'O', 'U', 'N', '_'], $value);
    }

    private function status(string $value): string
    {
        return match ($this->normalize($value)) {
            'ALTERNATIVA' => 'alternative',
            'NO_COTIZADO', 'NO_COTIZA', 'NO_DISPONIBLE', 'NO_DISPONIBILIDAD', 'AGOTADO' => 'not_quoted',
            default => 'quoted',
        };
    }
}
