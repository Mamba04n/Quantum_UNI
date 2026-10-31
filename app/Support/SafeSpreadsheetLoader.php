<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class SafeSpreadsheetLoader
{
    /**
     * Loads a spreadsheet safely, especially on Windows where Livewire temporary upload filenames
     * contain special characters (=, --, hash) that disrupt PHP's ZipArchive stream wrapper for xlsx.
     */
    public static function load(string $filePath): Spreadsheet
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls', 'csv', 'ods'], true)) {
            $extension = 'xlsx';
        }

        $cleanTemp = tempnam(sys_get_temp_dir(), 'qc_safe_') . '.' . $extension;
        copy($filePath, $cleanTemp);

        try {
            return IOFactory::load($cleanTemp);
        } finally {
            @unlink($cleanTemp);
        }
    }
}
