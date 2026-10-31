<?php

namespace App\Livewire\Administracion;

use App\Models\OrderStatusItem;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DispatchAuthorizationManager extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('dispatch_authorization.view'), 403);
    }

    public function authorizeItem(int $itemId, OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('dispatch_authorization.approve'), 403);

        $item = $this->pendingQuery()->findOrFail($itemId);
        $service->authorizePartialDispatch($item, auth()->user(), 'Autorizada desde el módulo administrativo de salidas parciales.');

        session()->flash('message', 'Salida parcial autorizada.');
    }

    public function authorizeOrder(int $orderStatusId, OrderStatusService $service): void
    {
        abort_unless(auth()->user()?->can('dispatch_authorization.approve'), 403);

        DB::transaction(function () use ($orderStatusId, $service): void {
            $this->pendingQuery()
                ->where('order_status_id', $orderStatusId)
                ->lockForUpdate()
                ->get()
                ->each(fn (OrderStatusItem $item) => $service->authorizePartialDispatch(
                    $item,
                    auth()->user(),
                    'Autorizada por pedido desde el módulo administrativo de salidas parciales.'
                ));
        });

        session()->flash('message', 'Se autorizaron todas las salidas parciales pendientes del pedido.');
    }

    public function render()
    {
        $pendingItems = $this->pendingQuery()
            ->latest('updated_at')
            ->get();

        $recentlyApproved = OrderStatusItem::query()
            ->with($this->relations())
            ->where('status', OrderStatusService::STATUS_APPROVED_PARTIAL_DISPATCH)
            ->latest('updated_at')
            ->limit(20)
            ->get();

        return view('livewire.administracion.dispatch-authorization-manager', [
            'pendingItems' => $pendingItems,
            'pendingOrders' => $pendingItems->groupBy('order_status_id'),
            'recentlyApproved' => $recentlyApproved,
            'canApprove' => auth()->user()?->can('dispatch_authorization.approve') ?? false,
        ]);
    }

    private function pendingQuery()
    {
        return OrderStatusItem::query()
            ->with($this->relations())
            ->where('status', OrderStatusService::STATUS_DISPATCH_AUTHORIZATION_REQUESTED);
    }

    private function relations(): array
    {
        return [
            'orderStatus.saleOrder.customer',
            'orderStatus.tracker',
            'product',
            'supplier',
            'events.user',
        ];
    }
}
