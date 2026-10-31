<?php

namespace App\Livewire\Compras;

use App\Domain\Compras\PurchaseOrders\Services\PurchaseOrderDocumentService;
use App\Models\ActivityLog;
use App\Models\Entity;
use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

#[Layout('components.layouts.app')]
class PurchaseOrderForm extends Component
{
    use WithFileUploads;

    public $supplier_id = '';

    public $reference_number = '';

    public $order_date;

    public $expected_date;

    public $currency = 'USD';

    public $items = [];

    public $total_fob = 0;

    public $bulkFile;

    public $showBulkModal = false;

    public $bulkPreview = [];

    public bool $showQuickSupplierModal = false;

    public string $newSupplierName = '';

    public string $newSupplierTaxId = '';

    public string $newSupplierContactName = '';

    public string $newSupplierPhone = '';

    public string $newSupplierEmail = '';

    public function mount()
    {
        abort_unless(auth()->user()?->can('purchases.order.create'), 403);
        $this->order_date = date('Y-m-d');
        $this->expected_date = date('Y-m-d', strtotime('+15 days'));

        $count = PurchaseOrder::count() + 1;
        $this->reference_number = 'OC-'.now()->format('y').'-'.str_pad($count, 4, '0', STR_PAD_LEFT);

        $this->addItem();
    }

    public function addItem()
    {
        abort_unless(auth()->user()?->can('purchases.order.create'), 403);
        $this->items[] = [
            'product_id' => '',
            'part_name' => '',
            'quantity' => 1,
            'unit_price' => 0,
            'total_price' => 0,
        ];
    }

    public function removeItem($index)
    {
        abort_unless(auth()->user()?->can('purchases.order.create'), 403);
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateTotal();
    }

    public function openBulkModal()
    {
        $this->showBulkModal = true;
        $this->bulkFile = null;
        $this->bulkPreview = [];
    }

    public function closeBulkModal()
    {
        $this->showBulkModal = false;
        $this->bulkFile = null;
        $this->bulkPreview = [];
    }

    public function openQuickSupplierModal(): void
    {
        abort_unless(auth()->user()?->can('purchases.order.create'), 403);

        $this->resetQuickSupplierForm();
        $this->showQuickSupplierModal = true;
    }

    public function closeQuickSupplierModal(): void
    {
        $this->showQuickSupplierModal = false;
        $this->resetQuickSupplierForm();
    }

    public function createQuickSupplier(): void
    {
        abort_unless(auth()->user()?->can('purchases.order.create'), 403);

        $data = $this->validate([
            'newSupplierName' => ['required', 'string', 'max:255'],
            'newSupplierTaxId' => ['nullable', 'string', 'max:80'],
            'newSupplierContactName' => ['nullable', 'string', 'max:255'],
            'newSupplierPhone' => ['nullable', 'string', 'max:50'],
            'newSupplierEmail' => ['nullable', 'not_regex:/[\r\n]/', 'email', 'max:255'],
        ]);

        $supplierName = trim($data['newSupplierName']);
        $existingSupplier = Entity::query()
            ->where('type', 'proveedor')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($supplierName)])
            ->first();

        if ($existingSupplier) {
            $this->supplier_id = (string) $existingSupplier->id;
            $this->closeQuickSupplierModal();
            $this->dispatch('notify', ['type' => 'info', 'message' => 'El proveedor ya existía y fue seleccionado.']);

            return;
        }

        $supplier = Entity::create([
            'name' => $supplierName,
            'type' => 'proveedor',
            'rfc_ruc' => trim($data['newSupplierTaxId'] ?? '') ?: null,
            'contact_name' => trim($data['newSupplierContactName'] ?? '') ?: null,
            'phone_mobile' => trim($data['newSupplierPhone'] ?? '') ?: null,
            'email' => trim($data['newSupplierEmail'] ?? '') ?: null,
            'status' => 'activo',
        ]);

        $this->supplier_id = (string) $supplier->id;

        ActivityLog::log('compras', 'supplier.created', "Proveedor {$supplier->name} creado desde una orden de compra.", [
            'supplier_id' => $supplier->id,
        ]);

        $this->closeQuickSupplierModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Proveedor creado y seleccionado.']);
    }

    public function updatedItems()
    {
        $this->calculateTotal();
    }

    public function updatedBulkFile()
    {
        $this->importFromExcel();
    }

    public function calculateTotal()
    {
        $this->total_fob = 0;
        foreach ($this->items as $key => $item) {
            $qty = (float) ($item['quantity'] ?: 0);
            $price = (float) ($item['unit_price'] ?: 0);
            $total = $qty * $price;
            $this->items[$key]['total_price'] = $total;
            $this->total_fob += $total;
        }
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function () {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Partidas Orden de Compra');

            $logoPath = public_path('images/quantum-logo.png');
            if (file_exists($logoPath)) {
                $drawing = new Drawing;
                $drawing->setName('Logo Quantum');
                $drawing->setDescription('Logo Quantum');
                $drawing->setPath($logoPath);
                $drawing->setCoordinates('A1');
                $drawing->setOffsetX(10);
                $drawing->setOffsetY(4);
                $drawing->setHeight(46);
                $drawing->setWorksheet($sheet);
            }

            $sheet->mergeCells('A1:D1');
            $sheet->setCellValue('A1', '       QUANTUM SOLUCIONES — PLANTILLA DE ORDEN DE COMPRA');
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 12, 'bold' => true, 'color' => ['rgb' => '0F172A']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFFFF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(36);

            $sheet->mergeCells('A2:D2');
            $sheet->setCellValue('A2', '* Sustituye la fila de ejemplo y agrega una partida por fila. Cantidad y precio deben ser valores numéricos.');
            $sheet->getStyle('A2')->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 9, 'italic' => true, 'color' => ['rgb' => '475569']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);
            $sheet->getRowDimension(2)->setRowHeight(20);

            $sheet->fromArray([
                'ID / CÓDIGO (OPCIONAL)',
                'NOMBRE DEL PRODUCTO O CONSUMIBLE',
                'CANTIDAD',
                'PRECIO UNITARIO',
            ], null, 'A3');
            $sheet->getStyle('A3:D3')->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 10, 'bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E293B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '334155']]],
            ]);
            $sheet->getRowDimension(3)->setRowHeight(28);

            $sheet->fromArray([
                'EJEMPLO-001',
                '[EJEMPLO] Resma de papel carta (borra o sustituye esta fila)',
                2,
                8.50,
            ], null, 'A4');
            $sheet->getStyle('A4:D4')->applyFromArray([
                'font' => ['name' => 'Calibri', 'size' => 10, 'italic' => true, 'color' => ['rgb' => '64748B']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            ]);

            for ($row = 5; $row <= 30; $row++) {
                $sheet->getStyle("A{$row}:D{$row}")->applyFromArray([
                    'font' => ['name' => 'Calibri', 'size' => 10, 'color' => ['rgb' => '1E293B']],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(20);
            }

            $sheet->getStyle('C4:D30')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('C4:C30')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('D4:D30')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getColumnDimension('A')->setWidth(24);
            $sheet->getColumnDimension('B')->setWidth(52);
            $sheet->getColumnDimension('C')->setWidth(14);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->freezePane('A4');
            $sheet->setAutoFilter('A3:D30');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'Plantilla_Orden_Compra_Quantum.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    // --- Excel Import Logic ---
    public function importFromExcel()
    {
        if (! $this->bulkFile) {
            $this->dispatch('notify', ['type' => 'error', 'message' => 'Selecciona un archivo primero.']);

            return;
        }

        try {
            $filePath = $this->bulkFile->getRealPath();
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, false, false);

            if (empty($rows)) {
                $this->addError('bulkFile', 'El archivo está vacío.');
                return;
            }

            // Detectar fila de encabezados
            $headerRowIndex = null;
            $headerMap = null;

            foreach ($rows as $rowIndex => $row) {
                $cols = array_map(fn ($val) => trim((string) ($val ?? '')), (array) $row);
                if (empty(array_filter($cols, fn ($v) => $v !== ''))) {
                    continue;
                }

                $map = ['name' => null, 'code' => null, 'quantity' => null, 'price' => null];
                $matched = 0;
                foreach ($cols as $idx => $cell) {
                    $norm = mb_strtolower(trim($cell));
                    $norm = str_replace(['_', '-', '.', '/', '#', '*', ':', '(', ')'], ' ', $norm);
                    $norm = str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $norm);
                    $norm = trim(preg_replace('/\s+/', ' ', $norm) ?? '');

                    if ($map['name'] === null && in_array($norm, ['descripcion', 'concepto', 'nombre', 'producto', 'item', 'part name', 'repuesto', 'detalle'], true)) {
                        $map['name'] = $idx;
                        $matched++;
                    } elseif ($map['code'] === null && in_array($norm, ['codigo', 'id', 'codigo parte', 'sku', 'referencia', 'ref', 'n p', 'np', 'part code', 'part number'], true)) {
                        $map['code'] = $idx;
                        $matched++;
                    } elseif ($map['quantity'] === null && in_array($norm, ['cantidad', 'cant', 'qty', 'quantity', 'unidades', 'pzas'], true)) {
                        $map['quantity'] = $idx;
                        $matched++;
                    } elseif ($map['price'] === null && in_array($norm, ['precio', 'precio unitario', 'precio unitario estimado', 'costo', 'costo unitario', 'unit price', 'price'], true)) {
                        $map['price'] = $idx;
                        $matched++;
                    }
                }

                if ($matched >= 2 || ($map['quantity'] !== null && ($map['name'] !== null || $map['code'] !== null))) {
                    $headerRowIndex = $rowIndex;
                    $headerMap = $map;
                    break;
                }
            }

            $candidateRows = $headerRowIndex !== null ? array_slice($rows, $headerRowIndex + 1) : $rows;
            $preview = [];

            foreach ($candidateRows as $row) {
                $cols = array_map(fn ($val) => trim((string) ($val ?? '')), (array) $row);
                if (empty(array_filter($cols, fn ($v) => $v !== ''))) {
                    continue;
                }

                // Detectar si es fila de ejemplo
                $joined = mb_strtolower(implode(' ', array_filter($cols, fn ($v) => is_string($v) || is_numeric($v))));
                if (str_contains($joined, 'ejemplo') || str_contains($joined, 'sample') || str_contains($joined, 'muestra') || str_contains($joined, 'reemplazar esta fila')) {
                    continue;
                }

                if ($headerMap !== null) {
                    $name = $headerMap['name'] !== null ? trim((string) ($cols[$headerMap['name']] ?? '')) : '';
                    $code = $headerMap['code'] !== null ? trim((string) ($cols[$headerMap['code']] ?? '')) : '';
                    if ($name === '' && $code !== '') {
                        $name = $code;
                    }
                    $qty = $headerMap['quantity'] !== null ? $this->parseSpreadsheetNumber($cols[$headerMap['quantity']] ?? null) : 1;
                    $price = $headerMap['price'] !== null ? $this->parseSpreadsheetNumber($cols[$headerMap['price']] ?? null) : 0;
                } else {
                    $name = trim((string) ($cols[1] ?? $cols[0] ?? ''));
                    $qty = $this->parseSpreadsheetNumber($cols[2] ?? $cols[1] ?? null);
                    $price = $this->parseSpreadsheetNumber($cols[3] ?? $cols[2] ?? null);
                }

                if ($name !== '' && $qty > 0) {
                    $preview[] = [
                        'product_id' => '',
                        'part_name' => substr($name, 0, 255),
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'total_price' => $qty * $price,
                    ];
                }
            }

            if (count($preview) === 0) {
                $this->addError('bulkFile', 'No se encontraron productos válidos en el archivo.');

                return;
            }

            $this->bulkPreview = $preview;

        } catch (\Exception $e) {
            report($e);
            $this->addError('bulkFile', \App\Support\UserMessage::error($e, 'No se pudo leer el archivo. Revisa el formato e intenta de nuevo.'));
        }
    }

    public function applyBulkImport()
    {
        if (count($this->bulkPreview) === 0) {
            $this->importFromExcel();
            if (count($this->bulkPreview) === 0) {
                return;
            }
        }

        // Remover filas vacías previas
        $this->items = array_filter($this->items, function ($item) {
            return ! empty($item['product_id']) || ! empty($item['part_name']);
        });

        foreach ($this->bulkPreview as $row) {
            $this->items[] = $row;
        }

        $this->items = array_values($this->items);
        $this->calculateTotal();
        $this->closeBulkModal();
        $this->dispatch('notify', ['type' => 'success', 'message' => 'Productos importados.']);
    }

    public function save(PurchaseOrderDocumentService $docService)
    {
        abort_unless(auth()->user()?->can('purchases.order.create'), 403);

        $this->validate([
            'supplier_id' => 'required',
            'currency' => 'required|in:USD,NIO',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        if (empty($this->items)) {
            throw ValidationException::withMessages(['items' => 'Debes agregar al menos un producto.']);
        }

        try {
            DB::beginTransaction();

            $orderInfo = [
                'supplier_id' => $this->supplier_id,
                'reference_number' => $this->reference_number,
                'order_date' => $this->order_date,
                'expected_date' => $this->expected_date,
                'currency' => $this->currency,
                'total_fob' => $this->total_fob,
                'status' => 'draft',
            ];

            $purchaseOrder = PurchaseOrder::create($orderInfo);

            foreach ($this->items as $item) {
                $purchaseOrder->items()->create([
                    'product_id' => $item['product_id'] ?: null,
                    'part_name' => $item['part_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
            }

            $document = $docService->documentForDirectPurchaseOrder($purchaseOrder);
            $docService->generatePdf($document, auth()->user());

            DB::commit();

            session()->flash('message', 'Orden de compra creada.');

            return redirect()->route('dashboard');

        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            session()->flash('error', \App\Support\UserMessage::error($e, 'No se pudo guardar la orden de compra. Revisa los datos e intenta de nuevo.'));
        }
    }

    public function render()
    {
        return view('livewire.compras.purchase-order-form', [
            'suppliers' => Entity::where('type', 'proveedor')->orderBy('name')->get(),
            'products' => Product::where('active', true)->orderBy('name')->get(),
        ]);
    }

    private function resetQuickSupplierForm(): void
    {
        $this->reset([
            'newSupplierName',
            'newSupplierTaxId',
            'newSupplierContactName',
            'newSupplierPhone',
            'newSupplierEmail',
        ]);
        $this->resetValidation([
            'newSupplierName',
            'newSupplierTaxId',
            'newSupplierContactName',
            'newSupplierPhone',
            'newSupplierEmail',
        ]);
    }

    private function parseSpreadsheetNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = preg_replace('/[^0-9,.\-]/u', '', trim((string) $value)) ?? '';
        if ($normalized === '') {
            return 0;
        }

        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $normalized = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $normalized))
                : str_replace(',', '', $normalized);
        } elseif ($lastComma !== false) {
            $decimalPlaces = strlen($normalized) - $lastComma - 1;
            $normalized = $decimalPlaces <= 2
                ? str_replace(',', '.', $normalized)
                : str_replace(',', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : 0;
    }
}
