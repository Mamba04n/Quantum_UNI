<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Entity;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\PurchaseOrder;
use App\Models\SaleOrderItem;
use App\Models\PurchaseOrderItem;
use Carbon\Carbon;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Clients
        $clients = [];
        for ($i = 1; $i <= 5; $i++) {
            $clients[] = Entity::create([
                'name' => "Cliente Demo $i",
                'legal_name' => "Cliente Demo Legal $i S.A.",
                'type' => 'cliente',
                'email' => "cliente$i@example.com",
                'address' => "Dirección Cliente $i",
                'phone_mobile' => "555-000$i",
                'rfc_ruc' => "RUC-CLI-000$i",
                'status' => 'active',
                'country' => 'Nicaragua',
            ]);
        }

        // 2. Create Suppliers
        $suppliers = [];
        for ($i = 1; $i <= 5; $i++) {
            $suppliers[] = Entity::create([
                'name' => "Proveedor Demo $i",
                'legal_name' => "Proveedor Demo Legal $i S.A.",
                'type' => 'proveedor',
                'email' => "proveedor$i@example.com",
                'address' => "Dirección Proveedor $i",
                'phone_mobile' => "777-000$i",
                'rfc_ruc' => "RUC-PROV-000$i",
                'status' => 'active',
                'country' => 'China',
            ]);
        }

        // 3. Create Products
        $products = [];
        $types = ['pieza', 'servicio'];
        for ($i = 1; $i <= 10; $i++) {
            $products[] = Product::create([
                'name' => "Producto Demo $i",
                'brand' => "Marca $i",
                'description' => "Descripción del producto demo $i",
                'sku' => "SKU-" . Str::upper(Str::random(5)) . "-$i",
                'part_number' => "PN-" . Str::random(6),
                'type' => $types[array_rand($types)],
                'sales_price' => rand(100, 1000) + 0.99,
                'cost' => rand(50, 500) + 0.50,
                'stock_on_hand' => rand(10, 100),
                'active' => true,
            ]);
        }

        // 4. Create Sale Orders
        for ($i = 1; $i <= 5; $i++) {
            $client = $clients[array_rand($clients)];
            $so = SaleOrder::create([
                'reference' => 'SO-DEMO-' . Str::upper(Str::random(6)),
                'date' => Carbon::now()->subDays(rand(1, 30)),
                'customer_id' => $client->id,
                'client_name' => $client->name,
                'client_email' => $client->email,
                'client_ruc' => $client->rfc_ruc,
                'status' => 'pending',
                'currency' => 'USD',
                'subtotal' => 0, // will calculate
                'tax_percent' => 15,
                'tax_amount' => 0,
                'total_amount' => 0,
            ]);

            $subtotal = 0;
            // Add items
            for ($j = 1; $j <= 3; $j++) {
                $product = $products[array_rand($products)];
                $qty = rand(1, 5);
                $price = $product->sales_price;
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;

                SaleOrderItem::create([
                    'sale_order_id' => $so->id,
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'total_price' => $lineTotal,
                ]);
            }
            
            $taxAmount = $subtotal * 0.15;
            $so->update([
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount
            ]);
        }

        // 5. Create Purchase Orders
        for ($i = 1; $i <= 5; $i++) {
            $supplier = $suppliers[array_rand($suppliers)];
            $po = PurchaseOrder::create([
                'reference_number' => 'PO-DEMO-' . Str::upper(Str::random(6)),
                'supplier_id' => $supplier->id,
                'status' => 'draft',
                'order_date' => Carbon::now()->subDays(rand(1, 30)),
                'currency' => 'USD',
                'total_fob' => 0, // will calculate
            ]);

            $total = 0;
            // Add items
            for ($j = 1; $j <= 3; $j++) {
                $product = $products[array_rand($products)];
                $qty = rand(10, 50);
                $cost = $product->cost;
                $lineTotal = $qty * $cost;
                $total += $lineTotal;

                try {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $po->id,
                        'product_id' => $product->id,
                        'part_name' => $product->name,
                        'quantity' => $qty,
                        'unit_price' => $cost,
                        'total_price' => $lineTotal,
                        'currency' => 'USD',
                    ]);
                } catch (\Exception $e) {
                    echo "Erro: " . $e->getMessage() . "\n";
                }
            }
            
            $po->update([
                'total_fob' => $total,
            ]);
        }
    }
}
