<?php

namespace App\Application\Http\Controllers;

use App\Application\Http\Requests\StoreShipmentRequest;
use App\Domain\Logistica\Events\PackageShippedByCargoTrans;
use App\Http\Resources\Api\V1\ShipmentResource;
use App\Infrastructure\Audit\AuditTrailService;
use App\Models\LogisticsShipment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class EnvioLogisticaController
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $shipment = LogisticsShipment::query()->create([
            'order_id' => $payload['order_id'],
            'shipping_cost' => $payload['shipping_cost'],
            'currency' => strtoupper($payload['currency']),
            'user_id' => $request->user()->id,
            'client_id' => $payload['client_id'] ?? null,
            'client_machine_id' => $payload['client_machine_id'] ?? null,
            'shipped_at' => $payload['timestamp'],
        ]);

        $eventId = (string) Str::uuid();

        event(new PackageShippedByCargoTrans(
            eventId: $eventId,
            orderId: $shipment->order_id,
            shippingCost: (string) $shipment->shipping_cost,
            currency: $shipment->currency,
            userId: $request->user()->id,
            timestamp: $shipment->shipped_at->toDateTimeString(),
            clientId: $shipment->client_id,
            clientMachineId: $shipment->client_machine_id,
            productId: $shipment->product_id,
            quantity: $shipment->quantity,
        ));

        $this->auditTrailService->log(
            userId: $request->user()->id,
            action: 'logistics.shipment_registered',
            entityType: LogisticsShipment::class,
            entityId: $shipment->id,
            metadata: ['event_id' => $eventId, 'order_id' => $shipment->order_id]
        );

        return ApiResponse::success([
            'shipment' => (new ShipmentResource($shipment))->resolve(),
            'event_id' => $eventId,
        ], 'Shipment registered.', [], 201);
    }
}
