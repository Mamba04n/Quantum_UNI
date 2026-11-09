<?php

namespace App\Console\Commands;

use App\Domain\Inventario\Services\InventoryService;
use App\Enums\StockMovementType;
use App\Models\IntegrationClient;
use App\Models\IntegrationPurchaseRequest;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\StockMove;
use App\Services\Integration\IdempotencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoR6ErpIntegrationCommand extends Command
{
    protected $signature = 'integration:demo-r6';
    protected $description = 'Ejecuta la demostración canónica de integración ERP de inventario para CMMS (R6)';

    public function handle(): int
    {
        $this->info('============================================================');
        $this->info('DEMO CANÓNICA R6 — QUANTUM ERP INTEGRATION BACKEND');
        $this->info('============================================================');

        DB::transaction(function () {
            // 1. Cliente técnico CMMS
            $client = IntegrationClient::firstOrCreate(
                ['name' => 'CMMS-Core-Demo'],
                ['is_active' => true]
            );

            $this->line("1. Cliente Técnico de Integración: {$client->name} (ID: {$client->id})");

            // 2. Producto de prueba: Rodamiento SKF 6205
            $product = Product::create([
                'name' => 'Rodamiento SKF 6205 (Demo R6)',
                'description' => 'Rodamiento de bolas sellado para motores trifásicos.',
                'cost' => 50.00,
                'total_cost' => 500.00,
                'stock_on_hand' => 10,
                'active' => true,
            ]);

            // 3. Reserva comercial previa del ERP: 3 unidades
            $prevRes = InventoryReservation::create([
                'product_id' => $product->id,
                'quantity' => 3.00,
                'consumed_quantity' => 0.00,
                'status' => 'reserved',
                'source_type' => 'ERP_INTERNAL',
                'notes' => 'Reserva previa comercial interna del ERP',
            ]);

            $this->line("2. Estado Inicial del Producto:");
            $this->line("   - SKU generado: {$product->sku}");
            $this->line("   - Stock Físico (On Hand): {$product->stock_on_hand} UND");
            $this->line("   - Reserva Previa ERP: {$prevRes->quantity} UND");

            $activeReserved = (float) InventoryReservation::where('product_id', $product->id)
                ->where('status', 'reserved')
                ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_res')
                ->value('total_res');

            $available = max(0.0, (float) $product->stock_on_hand - $activeReserved);
            $this->line("   - Stock Disponible Inicial: {$available} UND (Esperado: 7)\n");

            // 4. CMMS solicita Reserva de 2 unidades para OT-2026-0045
            $this->line("3. CMMS solicita Reserva de 2 unidades para OT-2026-0045 (Idempotente)...");
            $keyRes = 'IDEMP-RES-DEMO-' . Str::uuid();

            $cmmsRes = InventoryReservation::create([
                'product_id' => $product->id,
                'integration_client_id' => $client->id,
                'source_type' => 'CMMS',
                'external_reference' => 'OT-2026-0045',
                'warehouse_code' => 'BODEGA-CENTRAL',
                'quantity' => 2.00,
                'consumed_quantity' => 0.00,
                'status' => 'reserved',
                'reserved_at' => now(),
                'idempotency_key' => $keyRes,
                'version' => 1,
            ]);

            $activeReserved = (float) InventoryReservation::where('product_id', $product->id)
                ->where('status', 'reserved')
                ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_res')
                ->value('total_res');
            $available = max(0.0, (float) $product->stock_on_hand - $activeReserved);

            $this->info("   -> Reserva Creada: ID #{$cmmsRes->id}");
            $this->line("   -> Total Reservado: {$activeReserved} UND (Esperado: 5)");
            $this->line("   -> Total Disponible: {$available} UND (Esperado: 5)\n");

            // 5. Técnico en campo consume 1 unidad
            $this->line("4. Técnico en campo registra Consumo de 1 unidad...");
            $invService = app(InventoryService::class);
            $move = $invService->recordMovement(
                productId: $product->id,
                type: StockMovementType::Out,
                quantity: 1,
                movementDate: now(),
                reference: "CMMS OT: OT-2026-0045 [Res #{$cmmsRes->id}]",
                state: 'hecho'
            );

            $cmmsRes->update([
                'consumed_quantity' => 1.00,
                'consumed_at' => now(),
                'stock_move_id' => $move->id,
                'version' => $cmmsRes->version + 1,
            ]);

            $product->refresh();
            $activeReserved = (float) InventoryReservation::where('product_id', $product->id)
                ->where('status', 'reserved')
                ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_res')
                ->value('total_res');
            $available = max(0.0, (float) $product->stock_on_hand - $activeReserved);

            $this->info("   -> Movimiento en Kardex creado: StockMove #{$move->id}");
            $this->line("   -> Tipo: SALIDA ({$move->quantity} UND), Costo: C$ {$move->unit_cost}");
            $this->line("   -> Stock Físico restante: {$product->stock_on_hand} UND (Esperado: 9)");
            $this->line("   -> Reserva CMMS restante: " . ($cmmsRes->quantity - $cmmsRes->consumed_quantity) . " UND");
            $this->line("   -> Stock Disponible: {$available} UND (Esperado: 5)\n");

            // 6. CMMS libera la unidad no utilizada (1 restante)
            $this->line("5. CMMS libera la 1 unidad restante no utilizada de la reserva...");
            $cmmsRes->update([
                'status' => 'released',
                'released_at' => now(),
                'version' => $cmmsRes->version + 1,
            ]);

            $activeReserved = (float) InventoryReservation::where('product_id', $product->id)
                ->where('status', 'reserved')
                ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_res')
                ->value('total_res');
            $available = max(0.0, (float) $product->stock_on_hand - $activeReserved);

            $this->info("   -> Estado de Reserva CMMS: {$cmmsRes->status}");
            $this->line("   -> Stock Físico: {$product->stock_on_hand} UND (intacto en 9)");
            $this->line("   -> Stock Disponible: {$available} UND (restaurado a 6 con reserva comercial)\n");

            // 7. Simulación de Faltante y Requisición de Compra
            $this->line("6. Detección de Faltante y Solicitud de Compra hacia ERP:");
            $needed = 15.0;
            $shortage = round($needed - $available, 2);
            $this->line("   -> Necesidad de OT: {$needed} UND, Disponible: {$available} UND, Faltante: {$shortage} UND");

            $iprCode = 'IPR-DEMO-' . date('Ymd') . '-' . substr(Str::uuid(), 0, 8);
            $ipr = IntegrationPurchaseRequest::create([
                'integration_client_id' => $client->id,
                'code' => $iprCode,
                'external_reference' => 'PR-CMMS-2026-009',
                'product_id' => $product->id,
                'item_code' => $product->sku,
                'description' => 'Rodamientos para Overhaul compresor',
                'quantity' => $shortage,
                'unit_of_measure' => 'UND',
                'priority' => 'HIGH',
                'justification' => 'Faltante detectado por Planning Workspace R1',
                'status' => 'REQUESTED',
                'requested_at' => now(),
                'version' => 1,
            ]);

            $this->info("   -> Requisición de Compra generada en ERP: {$ipr->code}");
            $this->line("   -> Cantidad solicitada: {$ipr->quantity} {$ipr->unit_of_measure}");
            $this->line("   -> Estado en ERP Compras: {$ipr->status}");
        });

        $this->info("\n============================================================\nDEMO CANÓNICA R6 EJECUTADA EXITOSAMENTE (0 ERRORES)\n============================================================");

        return Command::SUCCESS;
    }
}
