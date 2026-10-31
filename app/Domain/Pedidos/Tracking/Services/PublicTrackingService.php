<?php

namespace App\Domain\Pedidos\Tracking\Services;

use App\Models\ActivityLog;
use App\Models\OrderTracker;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\OrderTrackerService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicTrackingService
{
    public const LABELS = ['preparing' => 'Preparando tu pedido', 'ready' => 'Listo para despacho', 'in_transit' => 'En camino', 'delivered' => 'Entregado'];

    public function generate(User $user, SaleOrder $order, ?string $eta = null): OrderTracker
    {
        abort_unless($user->can('public_tracking.create'), 403);

        return DB::transaction(function () use ($user, $order, $eta) {
            $order = SaleOrder::lockForUpdate()->findOrFail($order->id);
            $tracker = OrderTracker::where('public_sale_order_id', $order->id)->first();
            if ($tracker?->public_tracking_code) {
                return $tracker;
            }
            $this->assertOperationalProgress($order, 'preparing');
            $tracker = app(OrderTrackerService::class)->trackerForOrder($order);
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $code = 'QS-';
                $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
                for ($i = 0; $i < 12; $i++) {
                    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                try {
                    DB::transaction(fn () => $tracker->update(['public_tracking_code' => $code, 'public_sale_order_id' => $order->id, 'delivery_status' => 'preparing', 'public_estimated_delivery_at' => $eta]));
                    break;
                } catch (QueryException $e) {
                    if (! OrderTracker::where('public_tracking_code', $code)->exists() || $attempt === 4) {
                        throw $e;
                    }
                }
            }
            $this->event($tracker, null, 'preparing', $user);

            return $tracker;
        }, 3);
    }

    public function advance(User $user, OrderTracker $tracker, string $expectedStatus): OrderTracker
    {
        abort_unless($user->can('public_tracking.update'), 403);

        return DB::transaction(function () use ($user, $tracker, $expectedStatus) {
            $tracker = OrderTracker::lockForUpdate()->findOrFail($tracker->id);
            if ($tracker->delivery_status !== $expectedStatus) {
                throw ValidationException::withMessages(['tracking' => 'El estado cambió. Actualiza la pantalla.']);
            }
            $states = array_keys(self::LABELS);
            $index = array_search($expectedStatus, $states, true);
            if ($index === false || $index === 3) {
                throw ValidationException::withMessages(['tracking' => 'No hay una transición disponible.']);
            }
            $next = $states[$index + 1];
            if ($next === 'delivered') {
                abort_unless($user->can('public_tracking.deliver'), 403);
            }
            $order = SaleOrder::findOrFail($tracker->public_sale_order_id);
            $this->assertOperationalProgress($order, $next);
            $tracker->update(['delivery_status' => $next, 'public_delivered_at' => $next === 'delivered' ? now() : null, 'public_delivered_by' => $next === 'delivered' ? $user->id : null]);
            $this->event($tracker, $expectedStatus, $next, $user);

            return $tracker;
        }, 3);
    }

    private function assertOperationalProgress(SaleOrder $order, string $status): void
    {
        $items = $order->orderStatus?->items;
        $field = match ($status) {
            'in_transit' => 'quantity_dispatched','delivered' => 'quantity_delivered', default => 'quantity_kardex_entry'
        };
        if (! $items || $items->isEmpty() || $items->contains(fn ($i) => (float) $i->quantity_ordered <= 0 || (float) $i->$field < (float) $i->quantity_ordered || in_array($i->status, ['cancelado_proveedor', 'cerrado_cancelado'], true))) {
            throw ValidationException::withMessages(['tracking' => match ($status) {
                'in_transit' => 'Registra primero el despacho completo en el flujo de entrega.', 'delivered' => 'Registra primero la entrega completa y su evidencia en el flujo de la orden.', default => 'La orden debe tener todas sus partidas recibidas en bodega.'
            }]);
        }
    }

    private function event(OrderTracker $tracker, ?string $old, string $new, User $user): void
    {
        $tracker->activities()->create(['action' => 'public_delivery.'.$new, 'description' => 'Estado público de entrega actualizado.', 'phase' => 'public_delivery', 'old_status' => $old, 'new_status' => $new, 'public_message' => self::LABELS[$new], 'user_id' => $user->id]);
        ActivityLog::log('ventas', 'public_delivery.'.$new, 'Seguimiento público actualizado.', ['tracker_id' => $tracker->id]);
    }

    public function publicData(OrderTracker $tracker): array
    {
        // Explicit allowlist. Never serialize the internal tracker or its relationships.
        return ['code' => $tracker->public_tracking_code, 'status' => $tracker->delivery_status, 'labels' => self::LABELS,
            'estimated' => $tracker->public_estimated_delivery_at,
            'count' => SaleOrder::find($tracker->public_sale_order_id)?->items()->count() ?? 0,
            'events' => $tracker->activities()->where('phase', 'public_delivery')->whereNotNull('public_message')->orderByDesc('id')->get(['public_message', 'created_at'])];
    }
}
