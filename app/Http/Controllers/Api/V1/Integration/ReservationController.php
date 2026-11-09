<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Domain\Inventario\Services\InventoryService;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Services\Integration\IdempotencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ReservationController extends Controller
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly InventoryService $inventoryService
    ) {}

    /**
     * Reserva atómica de inventario para una orden de trabajo de CMMS.
     * Previene race conditions mediante lockForUpdate a nivel de fila de producto.
     */
    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless(is_string($idempotencyKey) && filled($idempotencyKey), 422, 'El encabezado Idempotency-Key es obligatorio.');

        $input = $request->validate([
            'item_id' => 'required|integer',
            'quantity' => 'required|numeric|min:0.01',
            'warehouse_code' => 'sometimes|string|max:50',
            'external_reference' => 'required|string|max:100',
            'context' => 'sometimes|array',
        ]);

        $clientId = (int) $request->attributes->get('integration.client_id');
        $hash = $this->idempotency->canonicalHash($request->all());

        // Verificación de idempotencia
        $cached = $this->idempotency->check($clientId, $idempotencyKey, $hash);
        if ($cached) {
            return $cached;
        }

        $warehouseCode = $input['warehouse_code'] ?? 'BODEGA-CENTRAL';
        $quantity = (float) $input['quantity'];

        $result = DB::transaction(function () use ($input, $clientId, $idempotencyKey, $warehouseCode, $quantity) {
            /** @var Product $product */
            $product = Product::whereKey($input['item_id'])->lockForUpdate()->first();
            abort_unless($product, 404, 'El ítem solicitado no existe en el catálogo ERP.');

            // Calcular reservas activas actuales (descontando lo ya consumido)
            $reserved = (float) InventoryReservation::query()
                ->where('product_id', $product->id)
                ->where('status', 'reserved')
                ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_reserved')
                ->value('total_reserved');

            $available = max(0.0, (float) $product->stock_on_hand - $reserved);

            if ($quantity > $available) {
                $shortage = round($quantity - $available, 2);

                return response()->json([
                    'error' => [
                        'code' => 'INSUFFICIENT_STOCK',
                        'message' => "Stock insuficiente en bodega {$warehouseCode}. Solicitado: {$quantity}, Disponible: {$available}, Faltante: {$shortage}.",
                        'details' => [
                            'requested' => $quantity,
                            'available' => $available,
                            'shortage' => $shortage,
                        ],
                    ],
                ], 409, ['X-Shortage-Quantity' => (string) $shortage]);
            }

            $reservation = InventoryReservation::create([
                'product_id' => $product->id,
                'integration_client_id' => $clientId,
                'source_type' => 'CMMS',
                'external_reference' => $input['external_reference'],
                'warehouse_code' => $warehouseCode,
                'quantity' => $quantity,
                'consumed_quantity' => 0.00,
                'status' => 'reserved',
                'reserved_at' => now(),
                'idempotency_key' => $idempotencyKey,
                'external_context' => $input['context'] ?? null,
                'notes' => "Reserva automática generada desde CMMS para OT {$input['external_reference']}.",
                'version' => 1,
            ]);

            return [
                'reservation' => $reservation,
                'product' => $product,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $reservation = $result['reservation'];
        $product = $result['product'];

        $payload = [
            'data' => [
                'reservation_id' => (string) $reservation->id,
                'item_id' => (string) $product->id,
                'item_code' => (string) ($product->sku ?: $product->part_number ?: $product->id),
                'item_name' => (string) $product->name,
                'warehouse' => $reservation->warehouse_code,
                'quantity_reserved' => (float) $reservation->quantity,
                'quantity_consumed' => 0.00,
                'remaining' => (float) $reservation->quantity,
                'status' => 'reserved',
                'external_reference' => $reservation->external_reference,
                'created_at' => $reservation->created_at->toIso8601ZuluString(),
                'version' => $reservation->version,
            ],
        ];

        // Guardar registro de idempotencia
        $this->idempotency->record(
            $clientId,
            $idempotencyKey,
            $hash,
            'inventory.reserve',
            201,
            $payload,
            'inventory_reservation',
            $reservation->id
        );

        return response()->json($payload, 201);
    }

    /**
     * Liberación total o parcial de una reserva de inventario.
     */
    public function release(string $id, Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless(is_string($idempotencyKey) && filled($idempotencyKey), 422, 'El encabezado Idempotency-Key es obligatorio.');

        $input = $request->validate([
            'quantity' => 'sometimes|numeric|min:0.01',
            'reason' => 'sometimes|string|max:255',
        ]);

        $clientId = (int) $request->attributes->get('integration.client_id');
        $hash = $this->idempotency->canonicalHash($request->all());

        $cached = $this->idempotency->check($clientId, $idempotencyKey, $hash);
        if ($cached) {
            return $cached;
        }

        $result = DB::transaction(function () use ($id, $clientId, $input) {
            /** @var InventoryReservation $reservation */
            $reservation = InventoryReservation::whereKey($id)->lockForUpdate()->first();
            abort_unless($reservation, 404, 'La reserva especificada no existe.');
            abort_unless((int) $reservation->integration_client_id === $clientId, 404, 'Reserva no pertenece a este consumidor.');

            if ($reservation->status === 'released') {
                return [
                    'reservation' => $reservation,
                    'released_qty' => 0.0,
                    'remaining' => 0.0,
                ];
            }

            $remaining = max(0.0, (float) $reservation->quantity - (float) $reservation->consumed_quantity);
            $releaseQty = isset($input['quantity']) ? min($remaining, (float) $input['quantity']) : $remaining;

            if ($releaseQty >= $remaining) {
                $reservation->status = 'released';
                $reservation->released_at = now();
                $reservation->version = $reservation->version + 1;
                $reservation->save();
                $finalRemaining = 0.0;
            } else {
                $reservation->quantity = round((float) $reservation->quantity - $releaseQty, 2);
                $reservation->version = $reservation->version + 1;
                $reservation->save();
                $finalRemaining = max(0.0, (float) $reservation->quantity - (float) $reservation->consumed_quantity);
            }

            return [
                'reservation' => $reservation,
                'released_qty' => $releaseQty,
                'remaining' => $finalRemaining,
            ];
        });

        $reservation = $result['reservation'];

        $payload = [
            'data' => [
                'reservation_id' => (string) $reservation->id,
                'item_id' => (string) $reservation->product_id,
                'quantity_released' => (float) $result['released_qty'],
                'remaining_reserved' => (float) $result['remaining'],
                'status' => $reservation->status,
                'updated_at' => now()->toIso8601ZuluString(),
                'version' => $reservation->version,
            ],
        ];

        $this->idempotency->record(
            $clientId,
            $idempotencyKey,
            $hash,
            'inventory.release',
            200,
            $payload,
            'inventory_reservation',
            $reservation->id
        );

        return response()->json($payload, 200);
    }

    /**
     * Consumo real de inventario contra una reserva existente.
     * Descuenta stock_on_hand físico, genera movimiento en Kardex (StockMove)
     * y actualiza costos según las reglas de valoración de QuantumComplete.
     */
    public function consume(string $id, Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        abort_unless(is_string($idempotencyKey) && filled($idempotencyKey), 422, 'El encabezado Idempotency-Key es obligatorio.');

        $input = $request->validate([
            'quantity' => 'required|numeric|min:0.01',
            'external_reference' => 'sometimes|string|max:100',
            'notes' => 'sometimes|string|max:255',
        ]);

        $clientId = (int) $request->attributes->get('integration.client_id');
        $hash = $this->idempotency->canonicalHash($request->all());

        $cached = $this->idempotency->check($clientId, $idempotencyKey, $hash);
        if ($cached) {
            return $cached;
        }

        $consumeQty = (float) $input['quantity'];

        $result = DB::transaction(function () use ($id, $clientId, $input, $consumeQty) {
            /** @var InventoryReservation $reservation */
            $reservation = InventoryReservation::whereKey($id)->lockForUpdate()->first();
            abort_unless($reservation, 404, 'La reserva especificada no existe.');
            abort_unless((int) $reservation->integration_client_id === $clientId, 404, 'Reserva no pertenece a este consumidor.');

            if ($reservation->status !== 'reserved') {
                throw new HttpException(409, "La reserva no se encuentra en estado 'reserved' (Estado actual: {$reservation->status}).", null, [], 409);
            }

            $remainingReserved = max(0.0, (float) $reservation->quantity - (float) $reservation->consumed_quantity);
            if ($consumeQty > $remainingReserved) {
                throw new HttpException(409, "La cantidad a consumir ({$consumeQty}) supera la reserva restante ({$remainingReserved}). Sobreconsumo no autorizado.", null, [], 409);
            }

            /** @var Product $product */
            $product = Product::whereKey($reservation->product_id)->lockForUpdate()->firstOrFail();

            if ($consumeQty > (float) $product->stock_on_hand) {
                throw new HttpException(409, "Stock físico insuficiente en almacén para ejecutar la salida (Físico: {$product->stock_on_hand}).", null, [], 409);
            }

            // Registrar movimiento de salida real mediante el servicio oficial del ERP
            $extRef = $input['external_reference'] ?? $reservation->external_reference;
            $referenceText = "CMMS OT: {$extRef} [Res #{$reservation->id}]";

            $stockMove = $this->inventoryService->recordMovement(
                productId: $product->id,
                type: StockMovementType::Out,
                quantity: (int) round($consumeQty),
                movementDate: now(),
                reference: $referenceText,
                state: 'hecho'
            );

            // Actualizar la reserva
            $newConsumed = round((float) $reservation->consumed_quantity + $consumeQty, 2);
            $newRemaining = round((float) $reservation->quantity - $newConsumed, 2);
            $newStatus = ($newRemaining <= 0.001) ? 'consumed' : 'reserved';

            $reservation->consumed_quantity = $newConsumed;
            $reservation->consumed_at = now();
            $reservation->status = $newStatus;
            $reservation->stock_move_id = $stockMove->id;
            $reservation->version = $reservation->version + 1;
            $reservation->save();

            // Refrescar producto para consultar stock restante real y costo
            $product->refresh();

            // Calcular disponibilidad restante (descontando lo ya consumido)
            $activeReserved = (float) InventoryReservation::query()
                ->where('product_id', $product->id)
                ->where('status', 'reserved')
                ->selectRaw('COALESCE(SUM(quantity - consumed_quantity), 0) as total_reserved')
                ->value('total_reserved');

            $available = max(0.0, (float) $product->stock_on_hand - $activeReserved);

            return [
                'reservation' => $reservation,
                'stock_move' => $stockMove,
                'product' => $product,
                'remaining' => $newRemaining,
                'available' => $available,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $reservation = $result['reservation'];
        $stockMove = $result['stock_move'];
        $product = $result['product'];

        $unitCost = (float) ($stockMove->unit_cost > 0 ? $stockMove->unit_cost : ($product->cost ?: 0.0));
        $totalCost = (float) ($stockMove->cost_out > 0 ? $stockMove->cost_out : ($consumeQty * $unitCost));

        $payload = [
            'data' => [
                'reservation_id' => (string) $reservation->id,
                'stock_move_id' => (int) $stockMove->id,
                'item_id' => (string) $product->id,
                'quantity_consumed' => $consumeQty,
                'remaining_reserved' => (float) $result['remaining'],
                'stock_on_hand' => (float) $product->stock_on_hand,
                'available' => (float) $result['available'],
                'unit_cost' => $unitCost > 0 ? $unitCost : null,
                'total_cost' => $totalCost > 0 ? $totalCost : null,
                'currency' => 'NIO',
                'valuation_method' => 'PROMEDIO_PONDERADO',
                'status' => $reservation->status,
                'version' => $reservation->version,
            ],
        ];

        $this->idempotency->record(
            $clientId,
            $idempotencyKey,
            $hash,
            'inventory.consume',
            200,
            $payload,
            'inventory_reservation',
            $reservation->id
        );

        return response()->json($payload, 200);
    }
}
