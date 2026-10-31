<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Requests\Api\V1\Storefront\CartDetailsRequest;
use App\Http\Requests\Api\V1\Storefront\QuoteRequest;
use App\Models\Product;
use App\Support\ApiResponse;
use App\Support\StorefrontUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController
{
    public function details(CartDetailsRequest $request): JsonResponse
    {
        $cart = $this->buildCart($request->validated('cart'));

        return ApiResponse::success($cart, 'Cart details found.', [
            'count' => count($cart['items']),
        ]);
    }

    public function quote(QuoteRequest $request): JsonResponse
    {
        $cart = $this->buildCart($request->validated('cart'));

        if ($cart['items'] === []) {
            return ApiResponse::error('No valid products found in cart.', [], 422);
        }

        $ownerPhone = preg_replace('/[^0-9]/', '', (string) config('services.storefront.whatsapp_owner', ''));
        $orderCode = 'QS-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6));

        $orderId = DB::table('storefront_orders')->insertGetId([
            'codigo' => $orderCode,
            'guest_token' => hash('sha256', (string) $request->ip().'|'.(string) $request->userAgent()),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'canal' => 'web_api',
            'estado' => 'nuevo',
            'subtotal' => $cart['subtotal'],
            'total' => $cart['subtotal'],
            'moneda' => 'USD',
            'whatsapp_destino' => $ownerPhone !== '' ? $ownerPhone : null,
            'creado_en' => now(),
            'actualizado_en' => now(),
        ]);

        foreach ($cart['items'] as $item) {
            DB::table('storefront_order_items')->insert([
                'pedido_id' => $orderId,
                'product_id' => $item['id'],
                'nombre' => $item['name'],
                'sku' => $item['sku'],
                'cantidad' => $item['quantity'],
                'precio_unitario' => $item['unit_price'],
                'subtotal' => $item['line_total'],
                'imagen_url' => $item['image_url'],
                'detalle_url' => $item['detail_url'],
                'creado_en' => now(),
            ]);
        }

        $whatsappUrl = $ownerPhone !== ''
            ? 'https://wa.me/'.$ownerPhone.'?text='.rawurlencode($this->whatsappMessage($cart['items']))
            : null;

        if ($whatsappUrl !== null) {
            DB::table('storefront_orders')
                ->where('id', $orderId)
                ->update(['whatsapp_url' => $whatsappUrl, 'actualizado_en' => now()]);
        }

        return ApiResponse::success([
            'order_id' => $orderId,
            'order_code' => $orderCode,
            'subtotal' => $cart['subtotal'],
            'total' => $cart['subtotal'],
            'whatsapp_url' => $whatsappUrl,
        ], 'Quote request created.', [], 201);
    }

    private function buildCart(array $input): array
    {
        $quantities = collect($input)
            ->mapWithKeys(fn ($item) => [(int) $item['id'] => (int) $item['qty']])
            ->filter(fn ($qty, $id) => $id > 0 && $qty > 0);

        $products = Product::query()
            ->whereIn('id', $quantities->keys())
            ->where('active', true)
            ->get()
            ->keyBy('id');

        $items = [];
        $subtotal = 0.0;

        foreach ($quantities as $id => $quantity) {
            $product = $products->get($id);
            if (! $product) {
                continue;
            }

            $unitPrice = (float) ($product->sales_price ?? 0);
            $lineTotal = $unitPrice * $quantity;
            $image = $product->image_path;

            $items[] = [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sku' => (string) $product->sku,
                'part_number' => (string) ($product->part_number ?? ''),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'image_url' => StorefrontUrl::productImage($image),
                'detail_url' => \App\Support\StorefrontUrl::product($product),
            ];

            $subtotal += $lineTotal;
        }

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal, 2),
            'currency' => 'USD',
        ];
    }

    private function whatsappMessage(array $items): string
    {
        $lines = ['Hola, quiero realizar una solicitud de cotizacion:', 'Origen: Pagina web', ''];

        foreach ($items as $index => $item) {
            $lines[] = ($index + 1).'. '.$item['name'];
            $lines[] = '   Referencia: '.($item['part_number'] ?: $item['sku'] ?: 'N/D');
            $lines[] = '   Cantidad: '.$item['quantity'];
            $lines[] = '';
        }

        $lines[] = 'Viene de la pagina web de Quantum Soluciones.';

        return implode("\n", $lines);
    }
}
