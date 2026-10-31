<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\OrderStatus;
use App\Services\PreprintedInvoiceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Mpdf\Mpdf;

class PdfFacturaController extends Controller
{
    // ConversiÃ³n desde la referencia A4 del Word al talonario fÃ­sico carta.
    private const PREPRINT_SCALE = 0.9414;

    private const PREPRINT_X_OFFSET = 3.8;

    private const PREPRINT_Y_OFFSET = -1.7;

    /**
     * Vista de calibraciÃ³n local para el talonario preimpreso. El PDF contiene
     * solamente la sobreimpresiÃ³n; el papel fÃ­sico ya aporta el diseÃ±o de fondo.
     */
    public function preprintedPreview(Request $request, PreprintedInvoiceService $preprintedInvoices)
    {
        abort_unless(
            auth()->user()?->can('finance.invoice.view')
            || auth()->user()?->can('tracker.view_invoices')
            || auth()->user()?->can('kardex.output_from_order')
            || auth()->user()?->can('order_status.manage'),
            403
        );

        if ($request->filled('order_status_id')) {
            $status = OrderStatus::with(['saleOrder.customer', 'items.product', 'items.saleOrderItem'])
                ->findOrFail($request->integer('order_status_id'));
            $selectedIds = collect(explode(',', (string) $request->input('item_ids', '')))
                ->filter()->map(fn ($id) => (int) $id)->unique()->values();

            $selectedItems = $status->items
                ->when($selectedIds->isNotEmpty(), fn ($items) => $items->whereIn('id', $selectedIds))
                ->values();

            if ($selectedItems->isEmpty()) {
                $selectedItems = $status->items;
            }

            $requestedQuantity = $request->float('quantity');
            $quantities = $selectedItems->mapWithKeys(function ($item) use ($selectedItems, $requestedQuantity) {
                $available = max(0, (float) $item->quantity_kardex_entry - (float) $item->quantity_kardex_output);
                $qty = $selectedItems->count() === 1 && $requestedQuantity > 0
                    ? $requestedQuantity
                    : ($available > 0 ? $available : ((float) $item->quantity_ordered ?: 1));

                return [$item->id => $qty];
            })->all();

            $invoiceNumber = trim((string) $request->input('invoice_number', ''));
            if ($invoiceNumber === '') {
                $invoiceNumber = '01';
            }

            $customData = [
                'name' => $request->input('customer_name'),
                'ruc' => $request->input('customer_ruc'),
                'address' => $request->input('customer_address'),
                'phone' => $request->input('customer_phone'),
                'issue_date' => $request->input('issue_date'),
                'due_date' => $request->input('due_date'),
                'condition' => $request->input('condition'),
                'seller' => $request->input('seller'),
                'apply_iva' => filter_var($request->input('apply_iva', false), FILTER_VALIDATE_BOOLEAN),
                'apply_ir' => filter_var($request->input('apply_ir', false), FILTER_VALIDATE_BOOLEAN),
                'iva_rate' => $request->filled('iva_rate') ? $request->float('iva_rate') / 100 : 0.15,
                'ir_rate' => $request->filled('ir_rate') ? $request->float('ir_rate') / 100 : 0.02,
            ];
            $customData = array_filter($customData, fn ($v) => $v !== null && $v !== '');

            $filename = 'machote-factura-'.$invoiceNumber.'.pdf';

            return response($preprintedInvoices->generate($status, $quantities, $invoiceNumber, auth()->user()?->name, $customData), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            ]);
        }

        $mpdf = new Mpdf([
            'format' => 'Letter',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);
        $mpdf->AddPage();

        $data = [
            'name' => 'Constructora y Transportes del Pacífico, S.A.',
            'address' => 'Km 12 Carretera a Masaya, del puente peatonal 350 metros al este, edificio azul local 4, Managua, Nicaragua',
            'phone' => '+505 8888-1234 / +505 2278-9900',
            'ruc' => 'J0310000123456',
            'issue_date' => now()->format('d/m/Y'),
            'due_date' => now()->addDays(30)->format('d/m/Y'),
            'condition' => 'credito',
            'seller' => 'T01 - Jovan Betanco',
            'items' => [
                ['code' => 'QTM-HYD-001', 'product_name' => 'Kit hidráulico para excavadora', 'quantity' => '2', 'amount' => 480.00],
                ['code' => 'QTM-FLT-204', 'product_name' => 'Filtro de combustible industrial', 'quantity' => '6', 'amount' => 95.50],
                ['code' => 'SERV-INS', 'product_name' => 'Servicio de instalación', 'quantity' => '1', 'amount' => 125.00],
            ],
        ];

        $subtotal = collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['amount']);
        $iva = 0.00;
        $ir = 0.00;
        $total = $subtotal + $iva - $ir;

        $this->printFittedText($mpdf, 32, 50.5, 141, $data['name'], 9.5);
        $this->printFittedText($mpdf, 30.5, 58.5, 142.5, $data['address'], 9.5);
        $this->printFittedText($mpdf, 28, 65.6, 145, $data['phone'], 9.5);
        $this->printFittedText($mpdf, 28, 72.8, 145, $data['ruc'], 9.5);
        $this->printDateSegments($mpdf, $data['issue_date'], [43.0, 52.7, 64.3]);
        $this->printDateSegments($mpdf, $data['due_date'], [139.5, 149.2, 160.7]);

        // Las casillas Crédito/Contado del talonario físico se marcan manualmente.

        foreach ($data['items'] as $index => $item) {
            $y = 99 + ($index * 7);
            $this->printFittedText($mpdf, 8, $y, 24, $item['code'], 7.5);
            $this->printFittedText($mpdf, 36.5, $y, 86.5, $item['product_name'], 7.5);
            $this->printFittedText($mpdf, 128, $y, 35, $item['quantity'], 8, 'R');
            $this->printFittedText($mpdf, 170, $y, 35, 'C$ '.number_format($item['quantity'] * $item['amount'], 2), 8, 'R');
        }

        $this->printFittedText($mpdf, 171, 219.5, 34, 'C$ '.number_format($subtotal, 2), 8, 'R');
        $this->printFittedText($mpdf, 171, 226.5, 34, 'C$ '.number_format($iva, 2), 8, 'R');
        $this->printFittedText($mpdf, 171, 233.5, 34, 'C$ '.number_format($ir, 2), 8, 'R');
        $this->printFittedText($mpdf, 171, 244.0, 34, 'C$ '.number_format($total, 2), 9, 'R');

        $filename = filled($data['invoice_number'] ?? null)
            ? 'Factura_'.$data['invoice_number'].'.pdf'
            : 'prueba-factura-preimpresa.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
        ]);
    }

    public function generate($invoiceId)
    {
        abort_unless(auth()->user()?->can('finance.invoice.view') || auth()->user()?->can('tracker.view_invoices'), 403);

        return $this->buildPdf($invoiceId);
    }

    public function clientDownload($invoiceId)
    {
        // El middleware 'signed' se encarga de proteger esta ruta
        return $this->buildPdf($invoiceId);
    }

    private function buildPdf($invoiceId)
    {
        $invoice = Invoice::with(['entity', 'saleOrder.items.product'])->findOrFail($invoiceId);

        $mpdf = new Mpdf([
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 20,
        ]);

        $html = view('pdf.invoice_template', compact('invoice'))->render();
        $mpdf->WriteHTML($html);

        $filename = 'Factura_'.($invoice->invoice_number ?: $invoice->id).'.pdf';

        return response($mpdf->Output($filename, 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function printFittedText(Mpdf $mpdf, float $x, float $y, float $width, string $text, float $preferredSize, string $align = 'L'): void
    {
        $x = self::PREPRINT_X_OFFSET + ($x * self::PREPRINT_SCALE);
        $y = self::PREPRINT_Y_OFFSET + ($y * self::PREPRINT_SCALE);
        $width *= self::PREPRINT_SCALE;
        $preferredSize *= self::PREPRINT_SCALE;
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $size = $preferredSize;
        $minimumSize = 6.2;

        $mpdf->SetFont('dejavusans', '', $size);
        while ($size > $minimumSize && $mpdf->GetStringWidth($text) > $width) {
            $size -= 0.2;
            $mpdf->SetFont('dejavusans', '', $size);
        }

        $truncated = false;
        while ($mpdf->GetStringWidth($text) > $width && mb_strlen($text) > 1) {
            $truncated = true;
            $text = mb_substr($text, 0, -2);
        }
        if ($truncated) {
            while ($mpdf->GetStringWidth(rtrim($text).'...') > $width && mb_strlen($text) > 1) {
                $text = mb_substr($text, 0, -1);
            }
            $text = rtrim($text).'...';
        }

        $mpdf->SetXY($x, $y - 3.6);
        $mpdf->Cell($width, 4.5 * self::PREPRINT_SCALE, $text, 0, 0, $align, false);
    }

    private function printDateSegments(Mpdf $mpdf, string $date, array $positions): void
    {
        $parsedDate = Carbon::createFromFormat('d/m/Y', $date);
        $this->printFittedText($mpdf, $positions[0], 82.5, 7, $parsedDate->format('d'), 8);
        $this->printFittedText($mpdf, $positions[1], 82.5, 7, $parsedDate->format('m'), 8);
        $this->printFittedText($mpdf, $positions[2], 82.5, 14, $parsedDate->format('Y'), 8);
    }
}
