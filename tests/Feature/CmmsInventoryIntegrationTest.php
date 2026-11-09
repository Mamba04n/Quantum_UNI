<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\IntegrationClient;
use App\Models\IntegrationIdempotencyKey;
use App\Models\IntegrationPurchaseRequest;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\StockMove;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmmsInventoryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private IntegrationClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = IntegrationClient::create([
            'name' => 'CMMS-Core-' . Str::uuid(),
            'is_active' => true,
        ]);
    }

    private function token(array $abilities = []): string
    {
        $defaultAbilities = [
            'integration:read',
            'integration:items:read',
            'integration:stock:read',
            'integration:inventory:reserve',
            'integration:inventory:release',
            'integration:inventory:consume',
            'integration:purchase-requests:create',
            'integration:purchase-requests:read',
        ];

        return $this->client->createToken('cmms-test', $abilities ?: $defaultAbilities, now()->addHour())->plainTextToken;
    }

    public function test_capabilities_announces_all_inventory_and_purchase_abilities(): void
    {
        $token = $this->token();

        $response = $this->withToken($token)
            ->getJson('/api/v1/integration/capabilities')
            ->assertOk();

        $capabilities = $response->json('data.capabilities');
        $this->assertIsArray($capabilities);
        $this->assertContains('items.read', $capabilities);
        $this->assertContains('stock.read', $capabilities);
        $this->assertContains('inventory.reserve', $capabilities);
        $this->assertContains('inventory.release', $capabilities);
        $this->assertContains('inventory.consume', $capabilities);
        $this->assertContains('purchase_requests.create', $capabilities);
        $this->assertContains('purchase_requests.read', $capabilities);
    }

    public function test_items_index_and_show_whitelist_dto(): void
    {
        $product = Product::create([
            'sku' => 'SKF-6205-2RS',
            'part_number' => 'SKF-6205',
            'name' => 'Rodamiento Rígido de Bolas SKF 6205',
            'description' => 'Rodamiento para motor eléctrico trifásico 15HP.',
            'sales_price' => 120.00,
            'cost' => 45.00,
            'stock_on_hand' => 10,
            'active' => true,
        ]);

        $token = $this->token();

        // 1. Index
        $indexRes = $this->withToken($token)
            ->getJson('/api/v1/integration/items?limit=10')
            ->assertOk();

        $items = $indexRes->json('data');
        $this->assertNotEmpty($items);

        $firstItem = collect($items)->firstWhere('id', (string) $product->id);
        $this->assertNotNull($firstItem);
        $this->assertEqualsCanonicalizing(
            ['id', 'sku', 'part_number', 'name', 'description', 'category', 'unit', 'active', 'updated_at', 'version'],
            array_keys($firstItem)
        );

        // Comprobar que no se exponen costos internos ni precios de venta en el catálogo de items
        $this->assertArrayNotHasKey('cost', $firstItem);
        $this->assertArrayNotHasKey('sales_price', $firstItem);
        $this->assertArrayNotHasKey('stock_on_hand', $firstItem);

        // 2. Show
        $showRes = $this->withToken($token)
            ->getJson('/api/v1/integration/items/' . $product->id)
            ->assertOk();

        $this->assertSame((string) $product->id, $showRes->json('data.id'));
        $this->assertSame('Rodamiento Rígido de Bolas SKF 6205', $showRes->json('data.name'));

        // 3. 404 para producto inexistente
        $this->withToken($token)
            ->getJson('/api/v1/integration/items/999999')
            ->assertNotFound();
    }

    public function test_items_search_and_cursor_pagination(): void
    {
        Product::create([
            'sku' => 'MOT-001',
            'part_number' => 'WEG-15HP',
            'name' => 'Motor Eléctrico WEG 15HP',
            'active' => true,
        ]);

        Product::create([
            'sku' => 'VAL-002',
            'part_number' => 'SPX-2IN',
            'name' => 'Válvula de Mariposa 2 pulgadas',
            'active' => true,
        ]);

        $token = $this->token();

        // Búsqueda por SKU
        $resSku = $this->withToken($token)
            ->getJson('/api/v1/integration/items?search=WEG')
            ->assertOk();
        $this->assertCount(1, $resSku->json('data'));
        $this->assertSame('Motor Eléctrico WEG 15HP', $resSku->json('data.0.name'));

        // Búsqueda por nombre
        $resName = $this->withToken($token)
            ->getJson('/api/v1/integration/items?search=Mariposa')
            ->assertOk();
        $this->assertCount(1, $resName->json('data'));
        $this->assertSame('Válvula de Mariposa 2 pulgadas', $resName->json('data.0.name'));
    }

    public function test_stock_endpoint_calculation_reflects_physical_and_reservations(): void
    {
        $product = Product::create([
            'sku' => 'SKF-6309',
            'part_number' => '6309-C3',
            'name' => 'Rodamiento SKF 6309 C3',
            'stock_on_hand' => 10,
            'active' => true,
        ]);

        // Crear una reserva comercial previa existente de 3 unidades
        InventoryReservation::create([
            'product_id' => $product->id,
            'quantity' => 3.00,
            'status' => 'reserved',
            'notes' => 'Reserva previa comercial',
        ]);

        $token = $this->token();

        $res = $this->withToken($token)
            ->getJson('/api/v1/integration/items/' . $product->id . '/stock')
            ->assertOk();

        $data = $res->json('data');
        $this->assertSame('OK', $data['status']);
        $this->assertEquals(10.0, $data['total_on_hand']);
        $this->assertEquals(3.0, $data['total_reserved']);
        $this->assertEquals(7.0, $data['total_available']);

        $warehouse = $data['warehouses'][0];
        $this->assertSame('BODEGA-CENTRAL', $warehouse['warehouse_code']);
        $this->assertEquals(10.0, $warehouse['on_hand']);
        $this->assertEquals(3.0, $warehouse['reserved']);
        $this->assertEquals(7.0, $warehouse['available']);
    }

    public function test_atomic_reservation_and_idempotency_workflow(): void
    {
        $product = Product::create([
            'sku' => 'FIL-001',
            'name' => 'Filtro Hidráulico Principal',
            'stock_on_hand' => 10,
            'active' => true,
        ]);

        InventoryReservation::create([
            'product_id' => $product->id,
            'quantity' => 3.00,
            'status' => 'reserved',
        ]);

        $token = $this->token();
        $idempotencyKey = 'IDEMP-RES-' . Str::uuid();

        $payload = [
            'item_id' => $product->id,
            'quantity' => 2.00,
            'warehouse_code' => 'BODEGA-CENTRAL',
            'external_reference' => 'OT-2026-0045',
            'context' => [
                'task_sequence' => 1,
                'technician_name' => 'Carlos Mendoza',
            ],
        ];

        // 1. Primera llamada: Debe crear la reserva
        $res1 = $this->withToken($token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/integration/inventory/reservations', $payload)
            ->assertStatus(201);

        $resData1 = $res1->json('data');
        $this->assertSame('reserved', $resData1['status']);
        $this->assertEquals(2.0, $resData1['quantity_reserved']);
        $this->assertSame('OT-2026-0045', $resData1['external_reference']);
        $reservationId = $resData1['reservation_id'];

        // Comprobar que en base de datos el disponible bajó a 5
        $stockCheck = $this->withToken($token)
            ->getJson('/api/v1/integration/items/' . $product->id . '/stock')
            ->assertOk();
        $this->assertEquals(5.0, $stockCheck->json('data.total_reserved'));
        $this->assertEquals(5.0, $stockCheck->json('data.total_available'));

        // 2. Reintento con misma clave de idempotencia y mismo payload: Debe devolver la misma respuesta sin crear otra reserva
        $res2 = $this->withToken($token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/integration/inventory/reservations', $payload)
            ->assertStatus(201);

        $this->assertSame($reservationId, $res2->json('data.reservation_id'));
        $this->assertTrue($res2->json('meta.idempotent_replayed'));

        // Comprobar que solo existe una reserva con esa clave
        $this->assertEquals(
            1,
            InventoryReservation::where('idempotency_key', $idempotencyKey)->count()
        );
    }

    public function test_idempotency_conflict_when_key_reused_with_different_payload(): void
    {
        $product = Product::create([
            'sku' => 'ACE-001',
            'name' => 'Aceite Sintético ISO VG 220',
            'stock_on_hand' => 20,
            'active' => true,
        ]);

        $token = $this->token();
        $idempotencyKey = 'IDEMP-CONFLICT-' . Str::uuid();

        // 1. Primera llamada con cantidad 2
        $this->withToken($token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 2.00,
                'external_reference' => 'OT-001',
            ])
            ->assertStatus(201);

        // 2. Segunda llamada con la misma clave pero cantidad 5 -> 409 Conflict
        $this->withToken($token)
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 5.00,
                'external_reference' => 'OT-001',
            ])
            ->assertStatus(409);
    }

    public function test_stock_insufficient_returns_409_with_shortage_details(): void
    {
        $product = Product::create([
            'sku' => 'RET-001',
            'name' => 'Retén de Eje de Alta Presión',
            'stock_on_hand' => 5,
            'active' => true,
        ]);

        $token = $this->token();

        // Intentar reservar 8 cuando solo hay 5 disponibles
        $res = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'IDEMP-SHORTAGE-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 8.00,
                'external_reference' => 'OT-SHORTAGE-01',
            ])
            ->assertStatus(409);

        $this->assertStringContainsString('Stock insuficiente', $res->json('error.message'));
        $this->assertSame('3', $res->headers->get('X-Shortage-Quantity'));

        // Comprobar que no se creó reserva en BD
        $this->assertEquals(0, InventoryReservation::where('product_id', $product->id)->count());
    }

    public function test_concurrent_reservations_competing_for_last_unit_only_one_wins(): void
    {
        $product = Product::create([
            'sku' => 'LAST-001',
            'name' => 'Último Repuesto Crítico',
            'stock_on_hand' => 1,
            'active' => true,
        ]);

        $token = $this->token();

        // Primer intento: Reserva 1 unidad -> Gana
        $res1 = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'COMP-01-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 1.00,
                'external_reference' => 'OT-WINNER',
            ])
            ->assertStatus(201);

        // Segundo intento: Intenta reservar 1 unidad -> Pierde con 409
        $res2 = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'COMP-02-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 1.00,
                'external_reference' => 'OT-LOSER',
            ])
            ->assertStatus(409);

        $this->assertEquals(1, InventoryReservation::where('product_id', $product->id)->where('status', 'reserved')->count());
        $this->assertEquals(0, Product::find($product->id)->stock_on_hand - 1);
    }

    public function test_release_reservation_restores_available_stock(): void
    {
        $product = Product::create([
            'sku' => 'REL-001',
            'name' => 'Empaque de Válvula',
            'stock_on_hand' => 10,
            'active' => true,
        ]);

        $token = $this->token();

        // 1. Reservar 2 unidades
        $resCreate = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'REL-KEY-1-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 2.00,
                'external_reference' => 'OT-REL-01',
            ])
            ->assertStatus(201);

        $reservationId = $resCreate->json('data.reservation_id');

        // 2. Liberar reserva
        $releaseKey = 'REL-KEY-2-' . Str::uuid();
        $resRelease = $this->withToken($token)
            ->withHeader('Idempotency-Key', $releaseKey)
            ->postJson("/api/v1/integration/inventory/reservations/{$reservationId}/release", [
                'reason' => 'OT cancelada por producción',
            ])
            ->assertOk();

        $this->assertSame('released', $resRelease->json('data.status'));
        $this->assertEquals(2.0, $resRelease->json('data.quantity_released'));

        // Comprobar que available vuelve a 10 y stock físico sigue intacto en 10
        $stockCheck = $this->withToken($token)
            ->getJson('/api/v1/integration/items/' . $product->id . '/stock')
            ->assertOk();

        $this->assertEquals(10.0, $stockCheck->json('data.total_on_hand'));
        $this->assertEquals(0.0, $stockCheck->json('data.total_reserved'));
        $this->assertEquals(10.0, $stockCheck->json('data.total_available'));

        // 3. Reintento idempotente de release (con la misma clave y mismo payload)
        $resReRelease = $this->withToken($token)
            ->withHeader('Idempotency-Key', $releaseKey)
            ->postJson("/api/v1/integration/inventory/reservations/{$reservationId}/release", [
                'reason' => 'OT cancelada por producción',
            ])
            ->assertOk();
        $this->assertTrue($resReRelease->json('meta.idempotent_replayed'));
    }

    public function test_consume_reservation_creates_stock_move_and_decreases_physical_stock(): void
    {
        $product = Product::create([
            'sku' => 'CON-001',
            'name' => 'Bujía de Encendido Especial',
            'cost' => 50.00,
            'total_cost' => 500.00,
            'stock_on_hand' => 10,
            'active' => true,
        ]);

        $token = $this->token();

        // 1. Reservar 2 unidades
        $resCreate = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'CON-RES-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 2.00,
                'external_reference' => 'OT-CON-01',
            ])
            ->assertStatus(201);

        $reservationId = $resCreate->json('data.reservation_id');

        // 2. Consumir 1 unidad
        $consumeKey = 'CON-KEY-' . Str::uuid();
        $consumeRes = $this->withToken($token)
            ->withHeader('Idempotency-Key', $consumeKey)
            ->postJson("/api/v1/integration/inventory/reservations/{$reservationId}/consume", [
                'quantity' => 1.00,
                'notes' => 'Instalado en compresor por técnico en turno matutino',
            ])
            ->assertOk();

        $data = $consumeRes->json('data');
        $this->assertEquals(1.0, $data['quantity_consumed']);
        $this->assertEquals(1.0, $data['remaining_reserved']);
        $this->assertEquals(9.0, $data['stock_on_hand']);
        $this->assertEquals(8.0, $data['available']);
        $this->assertEquals(50.0, $data['unit_cost']);
        $this->assertEquals(50.0, $data['total_cost']);
        $this->assertSame('NIO', $data['currency']);
        $this->assertSame('PROMEDIO_PONDERADO', $data['valuation_method']);

        // Comprobar que en base de datos existe exactamente un StockMove tipo salida
        $stockMoves = StockMove::where('product_id', $product->id)->get();
        $this->assertCount(1, $stockMoves);
        $this->assertEquals(-1, $stockMoves->first()->quantity);
        $this->assertStringContainsString('CMMS OT: OT-CON-01', $stockMoves->first()->reference);

        // Comprobar que el producto disminuyó físicamente a 9
        $product->refresh();
        $this->assertEquals(9, $product->stock_on_hand);

        // 3. Reintento de consumo con la misma key de idempotencia y mismo payload: No duplica movimiento en Kardex
        $consumeRes2 = $this->withToken($token)
            ->withHeader('Idempotency-Key', $consumeKey)
            ->postJson("/api/v1/integration/inventory/reservations/{$reservationId}/consume", [
                'quantity' => 1.00,
                'notes' => 'Instalado en compresor por técnico en turno matutino',
            ])
            ->assertOk();

        $this->assertTrue($consumeRes2->json('meta.idempotent_replayed'));
        $this->assertCount(1, StockMove::where('product_id', $product->id)->get());
    }

    public function test_overconsumption_is_forbidden_with_409(): void
    {
        $product = Product::create([
            'sku' => 'OVR-001',
            'name' => 'Sensor de Temperatura',
            'stock_on_hand' => 10,
            'active' => true,
        ]);

        $token = $this->token();

        // Reservar 1 unidad
        $resCreate = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'OVR-RES-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 1.00,
                'external_reference' => 'OT-OVR-01',
            ])
            ->assertStatus(201);

        $reservationId = $resCreate->json('data.reservation_id');

        // Intentar consumir 2 unidades contra la reserva de 1 -> 409
        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'OVR-CON-' . Str::uuid())
            ->postJson("/api/v1/integration/inventory/reservations/{$reservationId}/consume", [
                'quantity' => 2.00,
            ])
            ->assertStatus(409);
    }

    public function test_purchase_request_lifecycle_and_show(): void
    {
        $product = Product::create([
            'sku' => 'PUR-001',
            'name' => 'Filtro de Aire Compresor',
            'stock_on_hand' => 0,
            'active' => true,
        ]);

        $token = $this->token();
        $prKey = 'PR-KEY-' . Str::uuid();

        // 1. Crear solicitud de compra por faltante
        $createRes = $this->withToken($token)
            ->withHeader('Idempotency-Key', $prKey)
            ->postJson('/api/v1/integration/purchase-requests', [
                'item_id' => $product->id,
                'item_code' => $product->sku,
                'description' => 'Reemplazo urgente por saturación de elemento filtrante',
                'quantity' => 4.00,
                'unit_of_measure' => 'UND',
                'priority' => 'HIGH',
                'justification' => 'Compresor en riesgo de alarma de alta temperatura',
                'external_reference' => 'PR-CMMS-2026-009',
                'required_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(201);

        $prId = $createRes->json('data.id');
        $code = $createRes->json('data.code');
        $this->assertNotEmpty($code);
        $this->assertSame('REQUESTED', $createRes->json('data.status'));
        $this->assertSame('PR-CMMS-2026-009', $createRes->json('data.external_reference'));

        // 2. Consultar por ID
        $getRes = $this->withToken($token)
            ->getJson('/api/v1/integration/purchase-requests/' . $prId)
            ->assertOk();

        $this->assertSame($code, $getRes->json('data.code'));
        $this->assertEquals(4.0, $getRes->json('data.quantity'));
        $this->assertSame('HIGH', $getRes->json('data.priority'));
        $this->assertSame('REQUESTED', $getRes->json('data.status'));

        // 3. Reintento idempotente de creación
        $reCreateRes = $this->withToken($token)
            ->withHeader('Idempotency-Key', $prKey)
            ->postJson('/api/v1/integration/purchase-requests', [
                'item_id' => $product->id,
                'item_code' => $product->sku,
                'description' => 'Reemplazo urgente por saturación de elemento filtrante',
                'quantity' => 4.00,
                'unit_of_measure' => 'UND',
                'priority' => 'HIGH',
                'justification' => 'Compresor en riesgo de alarma de alta temperatura',
                'external_reference' => 'PR-CMMS-2026-009',
                'required_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(201);

        $this->assertTrue($reCreateRes->json('meta.idempotent_replayed'));
    }

    public function test_scopes_granular_authorization_enforced(): void
    {
        $product = Product::create([
            'sku' => 'SEC-001',
            'name' => 'Válvula de Seguridad',
            'stock_on_hand' => 5,
            'active' => true,
        ]);

        // Token solo con lectura de items: No puede reservar
        $readOnlyToken = $this->token(['integration:read', 'integration:items:read']);

        $this->withToken($readOnlyToken)
            ->withHeader('Idempotency-Key', 'SCOPE-TEST-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => $product->id,
                'quantity' => 1.00,
                'external_reference' => 'OT-SCOPE',
            ])
            ->assertForbidden();

        // Token con wildcard '*' no permitido por política de seguridad
        $wildcardToken = $this->token(['*']);
        $this->withToken($wildcardToken)
            ->getJson('/api/v1/integration/items')
            ->assertForbidden();
    }

    public function test_rate_limiting_enforces_20_writes_per_minute_with_retry_after(): void
    {
        $token = $this->token();
        $writeKey = 'integration:client:' . $this->client->id . ':write';

        // Simular 20 intentos de escritura
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($writeKey, 60);
        }

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'RATE-TEST-' . Str::uuid())
            ->postJson('/api/v1/integration/inventory/reservations', [
                'item_id' => 1,
                'quantity' => 1.0,
                'external_reference' => 'OT-RATE',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        RateLimiter::clear($writeKey);
    }
}
