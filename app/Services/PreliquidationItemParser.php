<?php
namespace App\Services;

use Illuminate\Validation\ValidationException;

class PreliquidationItemParser
{
    public function text(string $text): array
    {
        $lines = preg_split('/\r\n|\n|\r/', trim($text));
        $delimiter = str_contains($lines[0] ?? '', "\t") ? "\t" : (str_contains($lines[0] ?? '', ';') ? ';' : ',');
        return $this->rows(array_map(fn ($line) => str_getcsv($line, $delimiter, '"', '\\'), $lines));
    }

    public function rows(array $lines): array
    {
        $result = [];
        $columns = [0, 1, 2, 3, 4];
        foreach ($lines as $lineIndex => $line) {
            if (!array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== '')) continue;
            $labels = array_map(fn ($v) => preg_replace('/[^a-z0-9]/', '', strtolower((string) $v)), $line);
            $part = array_search('partnumber', $labels, true);
            if ($part !== false) {
                $columns = [$part, array_search('description', $labels, true), array_search('qty', $labels, true), array_search('unitprice', $labels, true), array_search('weightlb', $labels, true)];
                if ($columns[4] === false) $columns[4] = array_search('weight', $labels, true);
                if (in_array(false, $columns, true)) throw ValidationException::withMessages(['preliquidationPaste' => 'Encabezados requeridos: Part Number, Description, Qty, Unit Price, Weight.']);
                continue;
            }
            // The workbook footer is not an item; never import computed footer rows.
            if (trim((string) ($line[$columns[0]] ?? '')) === '' && count($line) > 5) continue;
            $row = ['quoted_part_number' => trim((string) ($line[$columns[0]] ?? '')), 'quoted_description' => trim((string) ($line[$columns[1]] ?? ''))];
            foreach (['quoted_quantity' => 2, 'unit_cost' => 3, 'weight_lb' => 4] as $field => $position) {
                $value = str_replace(['USD', '$', "\xc2\xa0", ' '], '', trim((string) ($line[$columns[$position]] ?? '')));
                if (str_contains($value, ',') && str_contains($value, '.')) {
                    $value = strrpos($value, ',') > strrpos($value, '.') ? str_replace(',', '.', str_replace('.', '', $value)) : str_replace(',', '', $value);
                } elseif (str_contains($value, ',')) {
                    $value = preg_match('/^\d{1,3}(,\d{3})+$/', $value) ? str_replace(',', '', $value) : str_replace(',', '.', $value);
                }
                if ($value === '' || !is_numeric($value) || (float) $value < 0) {
                    throw ValidationException::withMessages(['preliquidationPaste' => 'Fila '.($lineIndex + 1).': cantidad, precio y peso deben ser números no negativos.']);
                }
                $row[$field] = (float) $value;
            }
            if ($row['quoted_quantity'] <= 0 || $row['quoted_part_number'] === '' || $row['quoted_description'] === '') {
                throw ValidationException::withMessages(['preliquidationPaste' => 'Fila '.($lineIndex + 1).': completa parte, descripción y cantidad mayor que cero.']);
            }
            $result[] = $row;
            if (count($result) > 500) throw ValidationException::withMessages(['preliquidationPaste' => 'Importa hasta 500 filas por lote.']);
        }
        if (!$result) throw ValidationException::withMessages(['preliquidationPaste' => 'No se detectaron partidas.']);
        return $result;
    }
}
