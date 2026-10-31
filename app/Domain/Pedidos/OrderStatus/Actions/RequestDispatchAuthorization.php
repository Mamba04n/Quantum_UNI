<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestDispatchAuthorization
{
    public function __construct(
        private readonly OrderStatusActivity $activity,
    ) {}

    public function handle(OrderStatusItem $item, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

        if ((float) $item->quantity_kardex_entry <= (float) $item->quantity_kardex_output) {
            throw ValidationException::withMessages([
                'quantity' => 'No hay cantidad disponible en bodega para solicitar salida.',
            ]);
        }

        if ($item->status === OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED) {
            return $item;
        }

        if (! in_array($item->status, [
            OrderStatusService::STATUS_AVAILABLE_FOR_DISPATCH,
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE,
            OrderStatusService::STATUS_RECEIVED_WAREHOUSE_PARTIAL,
            OrderStatusService::STATUS_KARDEX_ENTRY,
            OrderStatusService::STATUS_KARDEX_ENTRY_PARTIAL,
        ], true)) {
            throw ValidationException::withMessages([
                'authorization' => 'La partida no está lista para solicitar autorización de salida.',
            ]);
        }

        $oldStatus = $item->status;
        $item->forceFill(['status' => OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED])->save();
        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'dispatch.authorization_requested',
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $oldStatus,
            OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED,
            'Autorización de salida solicitada a Operaciones.',
            $user,
            $notes,
        );

        return $item;
    }
}
