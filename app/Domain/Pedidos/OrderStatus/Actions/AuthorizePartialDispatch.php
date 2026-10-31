<?php

namespace App\Domain\Pedidos\OrderStatus\Actions;

use App\Domain\Pedidos\OrderStatus\Services\OrderStatusService;
use App\Domain\Pedidos\OrderStatus\Support\OrderStatusActivity;
use App\Models\OrderStatusItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AuthorizePartialDispatch
{
    public function __construct(
        private readonly OrderStatusActivity $activity
    ) {}

    public function handle(OrderStatusItem $item, ?User $user = null, ?string $notes = null): OrderStatusItem
    {
        $item = OrderStatusItem::whereKey($item->id)->lockForUpdate()->firstOrFail();

        if ((float) $item->quantity_kardex_entry <= (float) $item->quantity_kardex_output) {
            throw ValidationException::withMessages(['quantity' => 'No hay cantidad disponible en bodega para autorizar salida.']);
        }

        if (! in_array($item->status, [
            OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED,
            OrderStatusService::STATUS_WAITING_OTHER_ITEMS,
        ], true)) {
            throw ValidationException::withMessages([
                'authorization' => 'Primero debes solicitar autorización de salida a Operaciones.',
            ]);
        }

        $oldStatus = $item->status;
        $item->forceFill(['status' => OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH])->save();
        $item->loadMissing('orderStatus');

        $this->activity->record(
            $item->orderStatus,
            $item,
            'dispatch.partial_authorized',
            OrderStatusService::PHASE_KARDEX_OUTPUT,
            $oldStatus,
            OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH,
            'Salida parcial autorizada por operaciones.',
            $user,
            $notes
        );

        return $item;
    }
}
