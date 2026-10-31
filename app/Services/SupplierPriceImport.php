<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class SupplierPriceImport
{
    public const HEADERS = ['numero_parte', 'descripcion', 'cantidad', 'unidad', 'precio_unitario_usd'];

    public function spreadsheet(string $path): array
    {
        // Livewire upload names can contain characters that break ZipArchive on Windows.
        $temporary = tempnam(sys_get_temp_dir(), 'qc_price_');
        try {
            if (! copy($path, $temporary)) {
                throw new \RuntimeException('No se pudo preparar el archivo para su lectura.');
            }

            return $this->readSpreadsheet($temporary);
        } finally {
            @unlink($temporary);
        }
    }

    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        // Only the first sheet, 500 records and a bounded number of columns.
        $info = $reader->listWorksheetInfo($path);
        if (empty($info) || $info[0]['totalRows'] > 501 || $info[0]['totalColumns'] > 20) {
            throw ValidationException::withMessages(['importFile' => 'Utiliza una hoja con hasta 500 partidas y 20 columnas.']);
        }
        $reader->setLoadSheetsOnly($info[0]['worksheetName']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell($columnAddress, $row, $worksheetName = '')
            {
                return $row <= 501 && strlen($columnAddress) === 1 && $columnAddress <= 'T';
            }
        });
        $book = $reader->load($path);
        try {
            return $this->rows($book->getSheet(0)->toArray(null, false, false, false));
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function text(string $text): array
    {
        if (strlen($text) > 1024 * 1024) {
            throw ValidationException::withMessages(['paste' => 'El texto supera el límite de 1 MB.']);
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($text));
        $separator = str_contains($lines[0] ?? '', "\t") ? "\t" : (str_contains($lines[0] ?? '', ';') ? ';' : ',');

        return $this->rows(array_map(fn ($line) => str_getcsv($line, $separator, '"', ''), $lines));
    }

    private function rows(array $rows): array
    {
        $headers = array_map(fn ($h) => Str::snake(Str::ascii(mb_strtolower(trim(ltrim((string) $h, "\xEF\xBB\xBF"))))), array_shift($rows) ?? []);
        if (array_diff(self::HEADERS, $headers) || count($headers) !== count(array_unique($headers))) {
            throw ValidationException::withMessages(['importFile' => 'Encabezados requeridos: '.implode(', ', self::HEADERS).'.']);
        }
        $indexes = array_flip($headers);
        $result = [];
        foreach ($rows as $index => $row) {
            if (! array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue;
            }
            if (count($result) >= 500) {
                throw ValidationException::withMessages(['importFile' => 'Solo se permiten 500 partidas por cotización.']);
            }
            $values = array_map(fn ($key) => trim((string) ($row[$indexes[$key]] ?? '')), self::HEADERS);
            // Calculations and external workbook links are never evaluated.
            if (collect($values)->contains(fn ($v) => str_starts_with($v, '='))) {
                throw ValidationException::withMessages(['importFile' => 'La fila '.($index + 2).' contiene fórmulas. Pega sus valores antes de importar.']);
            }
            $result[] = array_combine(['part_number', 'description', 'quantity', 'unit', 'unit_price'], $values);
        }
        if (! $result) {
            throw ValidationException::withMessages(['importFile' => 'No se encontraron partidas para cargar.']);
        }

        return $result;
    }
}
