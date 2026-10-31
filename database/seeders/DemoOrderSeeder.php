<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Entity;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;

class DemoOrderSeeder extends Seeder
{
    public function run()
    {
        $client = Entity::where('type', 'cliente')->first();
        $products = Product::inRandomOrder()->limit(5)->get();

        $order = SaleOrder::create([
            'reference' => 'SO-' . now()->format('Ymd-His'),
            'customer_id' => $client?->id,
            'client_name' => $client?->name ?? 'Cliente Web',
            'client_email' => $client?->email ?? 'contacto@cliente.com',
            'shipping_address' => 'Direccion de prueba completa, Managua',
            'date' => now(),
            'currency' => 'USD',
            'status' => 'generated',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total_amount' => 0,
            'notes' => 'Solicitud de prueba generada via seeder para revision de 5 items completos',
        ]);

        $total = 0;
        foreach ($products as $product) {
            $qty = rand(1, 5);
            $price = (float) ($product->sales_price ?: rand(50, 500));
            $lineTotal = $qty * $price;
            $total += $lineTotal;

            SaleOrderItem::create([
                'sale_order_id' => $order->id,
                'product_id' => $product->id,
                'part_number' => $product->part_number ?: $product->sku,
                'description' => $product->name,
                'quantity' => $qty,
                'unit_price' => $price,
                'total_price' => $lineTotal,
            ]);
        }

        $order->update([
            'subtotal' => $total,
            'tax_amount' => $total * 0.15,
            'total_amount' => $total * 1.15,
        ]);

        echo 'Solicitud creada con exito: ' . $order->reference . ' con ' . $order->items()->count() . ' items.' . PHP_EOL;
    }
}
