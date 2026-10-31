<?php

namespace App\Services;

use App\Models\OrderStatus;
use App\Models\OrderStatusItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mpdf\Mpdf;

class PreprintedInvoiceService
{
    private const PREPRINT_SCALE = 0.9414;
    private const PREPRINT_X_OFFSET = 3.8;
    private const PREPRINT_Y_OFFSET = -1.7;

    /** @param array<int, float|int> $quantities */
    public function generate(OrderStatus $status, array $quantities, string $invoiceNumber, ?string $seller = null, array $customData = []): string
    {
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '') {
            throw ValidationException::withMessages(['dispatchInvoiceNumber' => 'Ingresa el numero del talonario antes de registrar la salida.']);
        }

        $data = $this->dataForOrder($status, $quantities, $invoiceNumber, $seller, $customData);
        $mpdf = new Mpdf([
            'format' => 'Letter',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);
        $mpdf->AddPage();
        $this->writeOverlay($mpdf, $data);

        return $mpdf->Output('', 'S');
    }

    /** @param array<int, float|int> $quantities */
    public function store(OrderStatus $status, array $quantities, string $invoiceNumber, ?string $seller = null, array $customData = []): string
    {
        $safeNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', trim($invoiceNumber)) ?: 'sin-numero';
        $path = "order-status/{$status->id}/invoices/".now()->format('Ymd_His_u')."/Factura_{$safeNumber}.pdf";

        if (! Storage::disk('private')->put($path, $this->generate($status, $quantities, $invoiceNumber, $seller, $customData))) {
            throw ValidationException::withMessages(['document' => 'No fue posible guardar la factura de salida.']);
        }

        return $path;
    }

    /** @param array<int, float|int> $quantities */
    private function dataForOrder(OrderStatus $status, array $quantities, string $invoiceNumber, ?string $seller, array $customData = []): array
    {
        $status->loadMissing(['saleOrder.customer', 'items.product', 'items.saleOrderItem']);
        $items = $status->items->whereIn('id', array_map('intval', array_keys($quantities)))->values();

        $lines = $items->map(function (OrderStatusItem $item) use ($quantities) {
            $quantity = (float) ($quantities[$item->id] ?? 0);
            $available = max(0, (float) $item->quantity_kardex_entry - (float) $item->quantity_kardex_output);
            if ($available <= 0) {
                $available = (float) $item->quantity_ordered > 0 ? (float) $item->quantity_ordered : 1.0;
            }
            if ($quantity <= 0) {
                $quantity = $available;
            }

            return [
                'code' => $item->part_number ?: $item->product?->sku ?: '-',
                'product_name' => $item->part_name ?: $item->product?->name ?: $item->saleOrderItem?->description ?: 'Producto',
                'quantity' => $quantity,
                'amount' => (float) ($item->saleOrderItem?->unit_price ?? 0),
            ];
        });

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['item' => 'No hay partidas disponibles para facturar en esta salida.']);
        }
        if ($lines->count() > 17) {
            throw ValidationException::withMessages(['item' => 'El talonario admite hasta 17 partidas por factura. Separa la salida en mas de una factura.']);
        }

        $order = $status->saleOrder;
        $customer = $order?->customer;

        $issueDate = !empty($customData['issue_date'])
            ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customData['issue_date']) ? \Carbon\Carbon::parse($customData['issue_date'])->format('d/m/Y') : $customData['issue_date'])
            : now()->format('d/m/Y');

        $dueDate = !empty($customData['due_date'])
            ? (preg_match('/^\d{4}-\d{2}-\d{2}$/', $customData['due_date']) ? \Carbon\Carbon::parse($customData['due_date'])->format('d/m/Y') : $customData['due_date'])
            : (($order?->payment_type === 'Credito' || $order?->payment_type === 'Crédito')
                ? now()->addDays(30)->format('d/m/Y')
                : now()->format('d/m/Y'));

        return [
            'name' => $customData['name'] ?? ($customer?->legal_name ?: $customer?->name ?: $order?->client_name ?: 'Cliente'),
            'address' => $customData['address'] ?? ($customer?->exact_address ?: $customer?->address ?: $order?->shipping_address ?: '-'),
            'phone' => $customData['phone'] ?? ($customer?->phone_mobile ?: $customer?->phone_home ?: '-'),
            'ruc' => $customData['ruc'] ?? ($customer?->rfc_ruc ?: $order?->client_ruc ?: '-'),
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'condition' => $customData['condition'] ?? (($order?->payment_type === 'Credito' || $order?->payment_type === 'Crédito') ? 'credito' : 'contado'),
            'seller' => $customData['seller'] ?? ($seller ?: 'T01'),
            'invoice_number' => $invoiceNumber,
            'apply_iva' => (bool) ($customData['apply_iva'] ?? false),
            'apply_ir' => (bool) ($customData['apply_ir'] ?? false),
            'iva_rate' => (float) ($customData['iva_rate'] ?? 0.15),
            'ir_rate' => (float) ($customData['ir_rate'] ?? 0.02),
            'items' => $lines->all(),
        ];
    }

    private function writeOverlay(Mpdf $mpdf, array $data): void
    {
        $subtotal = collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['amount']);
        $applyIva = !empty($data['apply_iva']);
        $applyIr = !empty($data['apply_ir']);
        $ivaRate = (float) ($data['iva_rate'] ?? 0.15);
        $irRate = (float) ($data['ir_rate'] ?? 0.02);
        $iva = $applyIva ? round($subtotal * $ivaRate, 2) : 0.00;
        $ir = $applyIr ? round($subtotal * $irRate, 2) : 0.00;
        $total = $subtotal + $iva - $ir;

        $this->printFittedText($mpdf, 32, 50.5, 141, $data['name'], 9.5);
        $this->printFittedText($mpdf, 30.5, 58.5, 142.5, $data['address'], 9.5);
        $this->printFittedText($mpdf, 28, 65.6, 145, $data['phone'], 9.5);
        $this->printFittedText($mpdf, 28, 72.8, 145, $data['ruc'], 9.5);
        $this->printDateSegments($mpdf, $data['issue_date'], [43.0, 52.7, 64.3]);
        $this->printDateSegments($mpdf, $data['due_date'], [139.5, 149.2, 160.7]);

        foreach ($data['items'] as $index => $item) {
            $y = 99 + ($index * 7);
            $this->printFittedText($mpdf, 8, $y, 24, $item['code'], 7.5);
            $this->printFittedText($mpdf, 36.5, $y, 86.5, $item['product_name'], 7.5);
            $this->printFittedText($mpdf, 128, $y, 35, (string) $item['quantity'], 8, 'R');
            $this->printFittedText($mpdf, 170, $y, 35, 'C$ '.number_format($item['quantity'] * $item['amount'], 2), 8, 'R');
        }

        $this->printFittedText($mpdf, 171, 219.5, 34, 'C$ '.number_format($subtotal, 2), 8, 'R');
        $this->printFittedText($mpdf, 171, 226.5, 34, $applyIva ? 'C$ '.number_format($iva, 2) : 'C$ 0.00', 8, 'R');
        $this->printFittedText($mpdf, 171, 233.5, 34, $applyIr ? 'C$ '.number_format($ir, 2) : 'C$ 0.00', 8, 'R');
        $this->printFittedText($mpdf, 171, 244.0, 34, 'C$ '.number_format($total, 2), 9, 'R');
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
        $parsedDate = \Carbon\Carbon::createFromFormat('d/m/Y', $date);
        $this->printFittedText($mpdf, $positions[0], 82.5, 7, $parsedDate->format('d'), 8);
        $this->printFittedText($mpdf, $positions[1], 82.5, 7, $parsedDate->format('m'), 8);
        $this->printFittedText($mpdf, $positions[2], 82.5, 14, $parsedDate->format('Y'), 8);
    }
}
