<?php

namespace Database\Seeders;

use App\Domain\Ventas\Pedidos\Services\DirectInventorySaleService;
use App\Models\Entity;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\PurchaseReceiptService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Caso sintético para estudiar; no es evidencia de operaciones empresariales. */
class AcademicCaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('El caso académico no está habilitado en producción.');
        }
        $key = '00000000-0000-4000-8000-000000000002';
        if (SaleOrder::where('idempotency_key', $key)->exists()) {
            return;
        }
        $admin = User::where('email', config('quantum_uni.admin_email'))->firstOrFail();
        Auth::setUser($admin);
        try {
            DB::transaction(function () use ($admin, $key): void {
                $supplier = Entity::firstOrCreate(
                    ['email' => 'proveedor@quantum-uni.test'],
                    ['name' => 'Proveedor académico E2', 'type' => 'proveedor', 'status' => 'active'],
                );
                $customer = Entity::firstOrCreate(
                    ['email' => 'cliente@quantum-uni.test'],
                    ['name' => 'Cliente académico E2', 'type' => 'cliente', 'status' => 'active'],
                );
                $product = Product::firstOrCreate(
                    ['name' => 'Filtro de aceite — CASO ACADÉMICO'],
                    ['part_number' => 'E2-FILTRO-ACADEMICO', 'type' => 'pieza',
                        'brand' => 'Caso académico', 'technical_category' => 'otro',
                        'stock_on_hand' => 0, 'cost' => 0, 'total_cost' => 0,
                        'sales_price' => 150, 'active' => true],
                );
                $purchase = PurchaseOrder::create([
                    'supplier_id' => $supplier->id, 'reference_number' => 'UNI-E2-OC-001',
                    'status' => 'ordered', 'order_date' => today(), 'total_fob' => 1000,
                ]);
                $line = $purchase->items()->create([
                    'product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100,
                    'total_price' => 1000, 'received_quantity' => 0,
                ]);
                app(PurchaseReceiptService::class)->receive($purchase, [$line->id => 10], $admin);
                app(DirectInventorySaleService::class)->create([
                    'customer_id' => $customer->id, 'currency' => 'NIO', 'payment_type' => 'Contado',
                    'payment_method' => 'Efectivo', 'payment_reference' => 'UNI-E2-CAJA',
                    'due_date' => today()->toDateString(), 'notes' => 'Operación sintética universitaria.',
                    'apply_tax' => false, 'idempotency_key' => $key,
                    'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => 150]],
                ], $admin);
            });
        } finally {
            Auth::forgetUser();
        }
        $this->command?->info('Caso académico: compra 10, venta 4, saldo 6. No son operaciones reales.');
    }
}
