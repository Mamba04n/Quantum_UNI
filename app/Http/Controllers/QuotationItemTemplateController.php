<?php

namespace App\Http\Controllers;

use App\Models\QuotationRequest;
use App\Models\SupplierRfqDocument;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuotationItemTemplateController extends Controller
{
    public function downloadClientOffer(QuotationRequest $quotationRequest): StreamedResponse
    {
        abort_unless(auth()->user()?->can('client_offer.view'), 403);

        $quotationRequest->loadMissing(['items.selectedSupplier']);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Precios Cliente');
        $reference = $quotationRequest->reference ?: "COT-{$quotationRequest->id}";

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', "QUANTUM SOLUCIONES — PRECIOS OFERTA CLIENTE {$reference}");
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'Complete PRECIO_CLIENTE_NIO y seleccione GARANTIA y 50_PLUS. No modifique ITEM_ID.');
        $sheet->fromArray([
            'ITEM_ID', 'PRODUCTO', 'PROVEEDOR', 'COSTO_NIO', 'CANTIDAD',
            'PRECIO_CLIENTE_NIO', 'GARANTIA', '50_PLUS',
        ], null, 'A4');

        foreach ($quotationRequest->items->values() as $index => $item) {
            $sheet->fromArray([
                $item->id,
                $item->part_name ?: $item->description,
                $item->selectedSupplier?->name ?: '',
                (float) ($item->supplier_unit_cost_nio ?? $item->unit_cost ?? 0),
                (float) $item->quantity,
                (float) ($item->unit_price ?? 0),
                $item->warranty_type ?: 'Ninguna',
                $item->has_warranty_extension ? 'SI' : 'NO',
            ], null, 'A'.($index + 5));
        }

        $lastRow = max(5, $quotationRequest->items->count() + 4);
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2:H2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        $sheet->getStyle('A4:H4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getStyle("A5:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        $sheet->getStyle("A5:E{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
        $sheet->getStyle("D5:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');

        foreach (range(5, $lastRow) as $row) {
            $warranty = $sheet->getCell("G{$row}")->getDataValidation();
            $warranty->setType(DataValidation::TYPE_LIST)->setAllowBlank(false)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle('Garantía no válida')->setError('Selecciona una garantía de la lista.')
                ->setFormula1('"Ninguna,A1,A2,B1,B2,C1,C2"');

            $plus = $sheet->getCell("H{$row}")->getDataValidation();
            $plus->setType(DataValidation::TYPE_LIST)->setAllowBlank(false)->setShowDropDown(true)->setShowErrorMessage(true)
                ->setErrorTitle('Valor no válido')->setError('Selecciona SI o NO.')
                ->setFormula1('"SI,NO"');
        }

        foreach (['A' => 11, 'B' => 34, 'C' => 24, 'D' => 15, 'E' => 12, 'F' => 22, 'G' => 15, 'H' => 14] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:H{$lastRow}");

        $filename = 'Precios_Oferta_Cliente_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $reference).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function downloadSupplierResponse(QuotationRequest $quotationRequest, ?SupplierRfqDocument $rfqDocument = null): StreamedResponse
    {
        abort_unless(auth()->user()?->can('supplier_bid.view') || auth()->user()?->can('supplier_bid.create'), 403);
        abort_if($rfqDocument && $rfqDocument->quotation_request_id !== $quotationRequest->id, 404);

        $quotationRequest->loadMissing('items');
        $rfqDocument?->loadMissing('supplier');
        $items = $quotationRequest->items;

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Respuesta Proveedor');

        $supplier = $rfqDocument?->supplier;
        $supplierName = $supplier?->name ?: 'PROVEEDOR PENDIENTE / ABIERTO';
        $supplierContact = $supplier?->contact_name ?: ($rfqDocument?->supplier_contact_name ?: '');
        $quoteRef = $quotationRequest->reference ?: "COT-{$quotationRequest->id}";
        $rfqNumber = $rfqDocument?->effective_rfq_number ?: ($rfqDocument?->rfq_number ?: "RFQ-{$quoteRef}");
        $supplierCurrency = $supplier?->currency ?: 'USD';
        $generationDate = now()->format('d/m/Y');

        // Logo Quantum (Original Negro sobre Fondo Blanco)
        $logoPath = public_path('images/quantum-logo.png');
        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo Quantum');
            $drawing->setDescription('Logo Quantum');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(4);
            $drawing->setHeight(46);
            $drawing->setWorksheet($sheet);
        }

        // Row 1: Main Title Banner (Fondo Blanco Limpio)
        $sheet->mergeCells('A1:S1');
        $sheet->setCellValue('A1', '       QUANTUM SOLUCIONES — SOLICITUD DE COTIZACIÓN DE PROVEEDOR');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 13, 'bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Row 2: Supplier & Quote Info Banner
        $sheet->mergeCells('A2:G2');
        $sheet->setCellValue('A2', "PROVEEDOR: {$supplierName}" . ($supplierContact ? " ({$supplierContact})" : ''));

        $sheet->mergeCells('H2:L2');
        $sheet->setCellValue('H2', "COTIZACIÓN: {$quoteRef} | RFQ: {$rfqNumber}");

        $sheet->mergeCells('M2:S2');
        $sheet->setCellValue('M2', "FECHA: {$generationDate} | MONEDA SUGERIDA: {$supplierCurrency}");

        $sheet->getStyle('A2:S2')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        // Row 3: Instructions Banner
        $sheet->mergeCells('A3:S3');
        $sheet->setCellValue('A3', '* Complete obligatoriamente: PRECIO_UNITARIO, TIEMPO_ENTREGA y GARANTIA para cada partida cotizada. No modifique la columna ITEM_ID.');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(20);

        // Row 4: Column Headers
        $headers = [
            'ITEM_ID', 'CODIGO_SOLICITADO', 'DESCRIPCION_SOLICITADA', 'CANTIDAD_SOLICITADA',
            'PRECIO_UNITARIO', 'MONEDA', 'TIEMPO_ENTREGA', 'GARANTIA', 'ESTADO', 'FECHA_ESTIMADA',
            'CODIGO_COTIZADO', 'DESCRIPCION_COTIZADA', 'CANTIDAD_COTIZADA', 'MARCA',
            'MODELO', 'NUMERO_SERIE', 'NOTAS', 'CONDICIONES_PAGO', 'VALIDEZ_HASTA',
        ];
        $sheet->fromArray($headers, null, 'A4');

        // Row 5 onwards: Line Items
        foreach ($items->values() as $index => $item) {
            $sheet->fromArray([
                $item->id,
                $item->part_number,
                $item->part_name ?: $item->description,
                (float) $item->quantity,
                null,
                $supplierCurrency,
                null,
                null,
                'COTIZADO',
                null,
                $item->part_number,
                $item->part_name ?: $item->description,
                (float) $item->quantity,
                $item->brand,
                $item->model,
                $item->serial_number,
                null,
                null,
                null,
            ], null, 'A'.($index + 5));
        }

        $lastRow = max(5, $items->count() + 4);

        // Header Styling by Sections
        // Section 1: Solicitado (A4:D4) - Slate
        $sheet->getStyle('A4:D4')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);

        // Section 2: Cotización Obligatoria (E4:H4) - Emerald
        $sheet->getStyle('E4:H4')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '047857']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '065F46']]],
        ]);

        // Section 3: Estado y Alternativas (I4:S4) - Slate
        $sheet->getStyle('I4:S4')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E293B']]],
        ]);

        // Data Rows Styling
        $sheet->getStyle("A5:S{$lastRow}")->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '1E293B']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Soft background on requested info (A5:D)
        $sheet->getStyle("A5:D{$lastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');

        // Pure white background and crisp borders on editable quote columns (E5:H)
        $sheet->getStyle("E5:H{$lastRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        // Formatting
        $sheet->getStyle("E5:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D5:D{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("F5:F{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("I5:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Freeze ONLY top rows (rows 1-4) so horizontal scrolling is 100% free and comfortable
        $sheet->freezePane('A5');
        $sheet->setAutoFilter("A4:S{$lastRow}");

        // Explicit, spacious and proportional column widths
        $columnWidths = [
            'A' => 10, // ITEM_ID
            'B' => 18, // CODIGO_SOLICITADO
            'C' => 32, // DESCRIPCION_SOLICITADA
            'D' => 10, // CANTIDAD_SOLICITADA
            'E' => 16, // PRECIO_UNITARIO
            'F' => 10, // MONEDA
            'G' => 18, // TIEMPO_ENTREGA
            'H' => 16, // GARANTIA
            'I' => 14, // ESTADO
            'J' => 14, // FECHA_ESTIMADA
            'K' => 18, // CODIGO_COTIZADO
            'L' => 25, // DESCRIPCION_COTIZADA
            'M' => 12, // CANTIDAD_COTIZADA
            'N' => 14, // MARCA
            'O' => 14, // MODELO
            'P' => 14, // NUMERO_SERIE
            'Q' => 25, // NOTAS
            'R' => 16, // CONDICIONES_PAGO
            'S' => 14, // VALIDEZ_HASTA
        ];
        foreach ($columnWidths as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getRowDimension(4)->setRowHeight(30);

        // Data Validation on ESTADO (Column I)
        for ($row = 5; $row <= $lastRow; $row++) {
            $validation = $sheet->getCell("I{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(false);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setErrorTitle('Estado no válido');
            $validation->setError('Selecciona COTIZADO, ALTERNATIVA o NO_DISPONIBLE.');
            $validation->setFormula1('"COTIZADO,ALTERNATIVA,NO_DISPONIBLE"');
        }

        $control = $spreadsheet->createSheet();
        $control->setTitle('Control');
        $control->fromArray([
            ['CAMPO', 'VALOR'],
            ['COTIZACION_ID', $quotationRequest->id],
            ['REFERENCIA', $quotationRequest->reference],
            ['RFQ_ID', $rfqDocument?->id],
            ['RFQ_NUMERO', $rfqDocument?->effective_rfq_number],
            ['PROVEEDOR_ID', $rfqDocument?->supplier_id],
            ['PROVEEDOR', $rfqDocument?->supplier?->name ?: 'RFQ abierto / proveedor pendiente'],
            ['INSTRUCCION_ESTADO', 'Usa COTIZADO, ALTERNATIVA o NO_DISPONIBLE. No elimines ITEM_ID.'],
        ], null, 'A1');
        $control->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F172A']],
        ]);
        $control->getStyle('A2:A8')->getFont()->setBold(true);
        $control->getColumnDimension('A')->setWidth(25);
        $control->getColumnDimension('B')->setWidth(75);
        $control->getStyle('B2:B7')->getProtection()->setLocked(true);
        $spreadsheet->setActiveSheetIndex(0);

        $supplierLabel = $rfqDocument?->supplier?->name ?: 'Proveedor_Pendiente';
        $filename = 'Respuesta_Proveedor_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $supplierLabel).'_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $quotationRequest->reference ?: (string) $quotationRequest->id).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Descarga la plantilla oficial en Excel (.xlsx) para carga masiva de ítems en cotizaciones.
     */
    public function download(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Partidas Cotización');

        // Logo Quantum (Original Negro sobre Fondo Blanco)
        $logoPath = public_path('images/quantum-logo.png');
        if (file_exists($logoPath)) {
            $drawing = new Drawing();
            $drawing->setName('Logo Quantum');
            $drawing->setDescription('Logo Quantum');
            $drawing->setPath($logoPath);
            $drawing->setCoordinates('A1');
            $drawing->setOffsetX(10);
            $drawing->setOffsetY(4);
            $drawing->setHeight(46);
            $drawing->setWorksheet($sheet);
        }

        // Row 1: Main Title Banner (Fondo Blanco Limpio)
        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', '       QUANTUM SOLUCIONES — PLANTILLA DE CARGA MASIVA DE PARTIDAS');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => ['rgb' => '0F172A']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Row 2: Instructions Banner
        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', '* Ingrese las partidas requeridas para la solicitud. Puede borrar o sobreescribir la fila de ejemplo.');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // Row 3: Column Headers
        $headers = [
            'CODIGO_PARTE', 'DESCRIPCION', 'CANTIDAD', 'UNIDAD',
            'MARCA', 'MODELO', 'NUMERO_SERIE', 'NOTAS',
        ];
        $sheet->fromArray($headers, null, 'A3');

        $sheet->getStyle('A3:H3')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(28);

        // Row 4: Example Row
        $exampleRow = [
            'EJEMPLO-001',
            '[EJEMPLO] Eje diferencial Trasero (Borrar o reemplazar esta fila)',
            1,
            'PZA',
            'CATERPILLAR',
            '320D',
            'SER-448102',
            'Fila de muestra - Escriba sus partidas reales aquí o debajo',
        ];
        $sheet->fromArray($exampleRow, null, 'A4');

        $sheet->getStyle('A4:H4')->applyFromArray([
            'font' => ['name' => 'Calibri', 'size' => 10, 'italic' => true, 'color' => ['rgb' => '64748B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);
        $sheet->getStyle('C4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(4)->setRowHeight(22);

        // Rows 5-25: Pre-formatted Data Rows
        for ($rowNum = 5; $rowNum <= 25; $rowNum++) {
            $sheet->getStyle("A{$rowNum}:H{$rowNum}")->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '1E293B']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
            ]);
            $sheet->getStyle("C{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("D{$rowNum}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension($rowNum)->setRowHeight(20);
        }

        // Freeze ONLY top rows (rows 1-3)
        $sheet->freezePane('A4');
        $sheet->setAutoFilter('A3:H25');

        // Column widths
        $columnWidths = [
            'A' => 20, // CODIGO_PARTE
            'B' => 38, // DESCRIPCION
            'C' => 12, // CANTIDAD
            'D' => 12, // UNIDAD
            'E' => 18, // MARCA
            'F' => 18, // MODELO
            'G' => 18, // NUMERO_SERIE
            'H' => 30, // NOTAS
        ];
        foreach ($columnWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $filename = 'Plantilla_Items_Cotizacion_Quantum.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
