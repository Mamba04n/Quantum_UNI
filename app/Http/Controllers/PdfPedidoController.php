<?php

namespace App\Http\Controllers;

use App\Models\SaleOrder;
use Carbon\Carbon;
use Mpdf\Mpdf;

class PdfPedidoController extends Controller
{
    public function generate(int $orderId)
    {
        abort_unless(auth()->user()?->can('direct_order.view') || auth()->user()?->can('tracker.view_sensitive_documents'), 403);

        return $this->buildPdf($orderId);
    }

    public function clientDownload(int $orderId)
    {
        // El middleware 'signed' se encarga de proteger esta ruta
        return $this->buildPdf($orderId);
    }

    private function buildPdf(int $orderId)
    {
        $order = SaleOrder::with('items')->findOrFail($orderId);

        // Configuramos mPDF en formato Hoja Carta (Letter).
        $mpdf = new Mpdf([
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15, // Márgenes normales
            'margin_bottom' => 20,
        ]);

        // Transformamos los items de la base de datos al formato que espera la vista
        $piezas = [];
        foreach ($order->items as $item) {
            $piezas[] = [
                'cantidad' => $item->quantity,
                'codigo' => $item->part_number ?? '',
                'descripcion' => $item->description,
                'p_unitario' => (float) $item->unit_price,
            ];
        }

        // 2. Generamos HTML
        $html = view('pdf.order_template', [
            'orderId' => $order->reference,
            'cliente' => $order->client_name,
            'fecha' => Carbon::parse($order->date)->isoFormat('D [de] MMMM [del] YYYY'),
            'piezas' => $piezas,
            'subtotal' => $order->subtotal,
            'iva' => $order->tax_amount,
            'total' => $order->total_amount,
            'shipping' => $order->shipping_address,
            'provider' => $order->provider_name,
            'payment' => $order->payment_type,
        ])->render();

        // 3. Escribimos el HTML
        $mpdf->WriteHTML($html);

        // 4. Mostramos el PDF directamente en el navegador
        return response($mpdf->Output('Orden_Trabajo_'.$order->reference.'.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Orden_Trabajo_'.$order->reference.'.pdf"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
