<?php

namespace App\Livewire\Clientes;

use App\Models\ClientMachine;
use App\Models\Entity;
use App\Models\Invoice;
use App\Models\QuotationRequest;
use App\Models\SaleOrder;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.customer-portal')]
class Portal extends Component
{
    public string $view = 'dashboard';

    public ?int $selectedOrderId = null;

    public string $trackingCode = '';

    public ?string $trackingError = null;

    public function mount(): void
    {
        if (request('section') === 'tickets') {
            $this->view = 'tickets';
        }

        if (request('section') === 'quotations') {
            $this->view = 'quotations';
        }

        $latestOrder = $this->customerOrdersQuery()->first();
        $this->selectedOrderId = $latestOrder?->id;
        $this->trackingCode = $latestOrder?->reference ?? '';
    }

    public function render()
    {
        $user = Auth::user();
        $entity = $this->customerEntity();
        $orders = $this->customerOrdersQuery()->get();
        $selectedOrder = $this->selectedOrder($orders);
        $activeOrders = $orders->reject(fn (SaleOrder $order) => $this->isClosedOrder($order));
        $completedOrders = $orders->filter(fn (SaleOrder $order) => $this->isClosedOrder($order));
        $machines = $entity
            ? ClientMachine::with('components')->where('entity_id', $entity->id)->get()
            : collect();
        $components = $machines->flatMap->components
            ->sortByDesc(fn ($component) => (float) $component->consumed_percentage)
            ->values();
        $tickets = Ticket::query()
            ->where('customer_id', $user->id)
            ->latest('updated_at')
            ->get();

        $quotations = QuotationRequest::query()
            ->with(['items'])
            ->where(function ($query) use ($user, $entity) {
                if ($user?->id) {
                    $query->where('user_id', $user->id);
                }
                if ($user?->email) {
                    $query->orWhere('quote_to_email', $user->email);
                }
                if ($entity?->email) {
                    $query->orWhere('quote_to_email', $entity->email);
                }
            })
            ->latest('id')
            ->get();

        return view('livewire.clientes.portal', [
            'user' => $user,
            'entity' => $entity,
            'orders' => $orders,
            'selectedOrder' => $selectedOrder,
            'activeOrders' => $activeOrders,
            'completedOrders' => $completedOrders,
            'stats' => $this->stats($orders),
            'machines' => $machines,
            'components' => $components,
            'tickets' => $tickets,
            'quotations' => $quotations,
            'profileCompletion' => $this->profileCompletion($user, $entity),
            'timeline' => $selectedOrder ? $this->timelineFor($selectedOrder) : [],
        ]);
    }

    public function setView(string $view): void
    {
        $this->view = $view;
        $this->trackingError = null;
        $this->dispatch('scroll-to-top');
    }

    public function viewOrder(int $orderId): void
    {
        $order = $this->customerOrdersQuery()->whereKey($orderId)->first();

        if (! $order) {
            $this->trackingError = 'No encontramos una orden asociada a tu cuenta con ese codigo.';

            return;
        }

        $this->selectedOrderId = $order->id;
        $this->trackingCode = $order->reference;
        $this->view = 'order_detail';
        $this->trackingError = null;
    }

    public function searchTrackingCode(): void
    {
        $code = trim($this->trackingCode);

        if ($code === '') {
            $this->trackingError = 'Ingresa el codigo de pedido o tracking.';

            return;
        }

        $order = $this->customerOrdersQuery()
            ->where(function ($query) use ($code) {
                $query->where('reference', $code)
                    ->orWhere('invoice_number', $code)
                    ->orWhereHas('orderStatus', fn ($statusQuery) => $statusQuery
                        ->where('administrative_tracking_code', $code)
                        ->orWhere('order_code', $code)
                        ->orWhereHas('tracker', fn ($tracker) => $tracker
                            ->where('operational_tracking_code', $code)
                            ->orWhere('main_reference', $code)));
            })
            ->first();

        if (! $order) {
            $this->trackingError = 'Ese codigo no esta asociado a tu cuenta.';

            return;
        }

        $this->selectedOrderId = $order->id;
        $this->trackingCode = $order->reference;
        $this->view = 'order_detail';
        $this->trackingError = null;
    }

    public function statusLabel(SaleOrder|string|null $order): string
    {
        return $this->statusMeta($order)['label'];
    }

    public function statusClasses(SaleOrder|string|null $order): string
    {
        return $this->statusMeta($order)['classes'];
    }

    public function progressFor(SaleOrder|string|null $order): int
    {
        return $this->statusMeta($order)['progress'];
    }

    public function productImageUrl($product): ?string
    {
        if (! $product) {
            return null;
        }

        if (! empty($product->image_path) && is_file(public_path('storage/'.$product->image_path))) {
            return asset('storage/'.$product->image_path);
        }

        return str_starts_with($product->image_path, 'http')
            ? $product->image_path
            : asset('storage/'.$product->image_path);
    }

    private function customerEntity(): ?Entity
    {
        $email = Auth::user()?->email;

        if (! $email) {
            return null;
        }

        return Entity::query()
            ->where('type', 'cliente')
            ->where(function ($query) use ($email) {
                $query->where('user_id', Auth::id())
                    ->orWhere('email', $email)
                    ->orWhere('contact_info', $email);
            })
            ->first();
    }

    private function customerOrdersQuery()
    {
        $email = Auth::user()?->email;
        $entity = $this->customerEntity();

        return SaleOrder::query()
            ->with([
                'items.product',
                'orderStatus.events',
                'orderStatus.items',
                'orderStatus.documents',
                'orderStatus.tracker',
                'customer',
                'sourceQuotationRequest',
                'invoices:id,sales_order_id',
            ])
            ->where(function ($query) use ($email, $entity) {
                if ($email) {
                    $query->where('client_email', $email);
                }

                if ($entity) {
                    $query->orWhere('customer_id', $entity->id);
                }
            })
            ->orderByDesc('date')
            ->orderByDesc('id');
    }

    private function selectedOrder($orders): ?SaleOrder
    {
        if ($this->selectedOrderId) {
            $selected = $orders->firstWhere('id', $this->selectedOrderId);

            if ($selected) {
                return $selected;
            }
        }

        $active = $orders->first(fn ($order) => ! in_array($this->statusMeta($order)['key'], ['delivered', 'cancelled'], true));

        return $active ?: $orders->first();
    }

    private function stats($orders): array
    {
        $states = $orders->mapWithKeys(fn (SaleOrder $order) => [$order->id => $this->statusMeta($order)['key']]);

        return [
            'total' => $orders->count(),
            'active' => $states->filter(fn ($state) => ! in_array($state, ['delivered', 'cancelled'], true))->count(),
            'in_transit' => $states->filter(fn ($state) => in_array($state, ['supplier', 'transit', 'customs', 'delivery'], true))->count(),
            'delivered' => $states->filter(fn ($state) => $state === 'delivered')->count(),
            'cancelled' => $states->filter(fn ($state) => $state === 'cancelled')->count(),
            'total_amount' => $orders->reject(fn ($order) => $states[$order->id] === 'cancelled')->sum('total_amount'),
            'average_amount' => $orders->reject(fn ($order) => $states[$order->id] === 'cancelled')->avg('total_amount') ?? 0,
            'last_order_date' => $orders->first()?->date,
        ];
    }

    private function profileCompletion($user, ?Entity $entity): int
    {
        $values = [
            $entity?->name ?: $user?->name,
            $entity?->email ?: $user?->email,
            $entity?->phone_mobile ?: $user?->phone,
            $entity?->address ?: $user?->address,
            $entity?->rfc_ruc,
        ];

        return (int) round((collect($values)->filter()->count() / count($values)) * 100);
    }

    private function statusMeta(SaleOrder|string|null $order): array
    {
        if (! $order instanceof SaleOrder) {
            return $this->legacyStatusMeta($order);
        }

        $status = $order->orderStatus?->administrative_status ?: $order->status;
        $phase = $order->orderStatus?->administrative_phase;

        if (in_array($status, ['entregado_total', 'cerrado', 'entregado'], true)) {
            return ['key' => 'delivered', 'label' => 'Entregado', 'progress' => 100, 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'];
        }
        if (in_array($status, ['cancelado', 'cancelado_proveedor'], true)) {
            return ['key' => 'cancelled', 'label' => 'Cancelado', 'progress' => 0, 'classes' => 'bg-rose-50 text-rose-700 ring-rose-100'];
        }
        if ($this->orderHasInvoice($order)) {
            return ['key' => 'invoiced', 'label' => 'Facturado / en cierre', 'progress' => 95, 'classes' => 'bg-slate-100 text-slate-800 ring-slate-300'];
        }

        return match ($phase) {
            'entregado' => ['key' => 'delivery', 'label' => 'Entrega en proceso', 'progress' => 95, 'classes' => 'bg-orange-50 text-orange-700 ring-orange-100'],
            'logistica', 'kardex_salida' => ['key' => 'delivery', 'label' => 'Preparando entrega', 'progress' => 88, 'classes' => 'bg-orange-50 text-orange-700 ring-orange-100'],
            'bodega', 'kardex_entrada' => ['key' => 'warehouse', 'label' => 'Recibido en bodega', 'progress' => 76, 'classes' => 'bg-violet-50 text-violet-700 ring-violet-100'],
            'aduana' => ['key' => 'customs', 'label' => 'En proceso aduanero', 'progress' => 66, 'classes' => 'bg-cyan-50 text-cyan-700 ring-cyan-100'],
            'transito' => ['key' => 'transit', 'label' => 'En tránsito internacional', 'progress' => 56, 'classes' => 'bg-cyan-50 text-cyan-700 ring-cyan-100'],
            'confirmacion_proveedor' => ['key' => 'supplier', 'label' => 'Confirmación del proveedor', 'progress' => 42, 'classes' => 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
            'pedido_extranjero' => ['key' => 'purchase', 'label' => 'Compra a proveedor', 'progress' => 32, 'classes' => 'bg-amber-50 text-amber-700 ring-amber-100'],
            default => $this->legacyStatusMeta($status),
        };
    }

    private function legacyStatusMeta(?string $status): array
    {
        return match ($status) {
            'generated' => ['key' => 'registered', 'label' => 'Orden generada', 'progress' => 12, 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
            'confirmed' => ['key' => 'confirmed', 'label' => 'Confirmado', 'progress' => 22, 'classes' => 'bg-blue-50 text-blue-700 ring-blue-100'],
            'pending_purchase', 'cotizacion' => ['key' => 'purchase', 'label' => 'Preparando compra', 'progress' => 30, 'classes' => 'bg-amber-50 text-amber-700 ring-amber-100'],
            'solicitado_proveedor' => ['key' => 'supplier', 'label' => 'Pedido a proveedor', 'progress' => 45, 'classes' => 'bg-indigo-50 text-indigo-700 ring-indigo-100'],
            'en_transito_aduanas' => ['key' => 'transit', 'label' => 'En tránsito de importación', 'progress' => 64, 'classes' => 'bg-cyan-50 text-cyan-700 ring-cyan-100'],
            'en_inventario_local' => ['key' => 'warehouse', 'label' => 'En bodega', 'progress' => 78, 'classes' => 'bg-violet-50 text-violet-700 ring-violet-100'],
            'en_ruta' => ['key' => 'delivery', 'label' => 'En ruta de entrega', 'progress' => 90, 'classes' => 'bg-orange-50 text-orange-700 ring-orange-100'],
            'entregado' => ['key' => 'delivered', 'label' => 'Entregado', 'progress' => 100, 'classes' => 'bg-emerald-50 text-emerald-700 ring-emerald-100'],
            'cancelado' => ['key' => 'cancelled', 'label' => 'Cancelado', 'progress' => 0, 'classes' => 'bg-rose-50 text-rose-700 ring-rose-100'],
            default => ['key' => 'processing', 'label' => 'En proceso', 'progress' => 10, 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
        };
    }

    private function orderHasInvoice(SaleOrder $order): bool
    {
        if ($order->relationLoaded('invoices') && $order->invoices->isNotEmpty()) {
            return true;
        }

        return Invoice::query()
            ->where(function ($query) use ($order): void {
                $query->where('sales_order_id', $order->id);
                if ($order->orderStatus?->id) {
                    $query->orWhere('order_status_id', $order->orderStatus->id);
                }
                if ($order->sourceQuotationRequest?->reference) {
                    $query->orWhere('cotizacion_ref', $order->sourceQuotationRequest->reference);
                }
            })
            ->exists();
    }

    private function isClosedOrder(SaleOrder $order): bool
    {
        return in_array($this->statusMeta($order)['key'], ['delivered', 'cancelled'], true);
    }

    private function timelineFor(SaleOrder $order): array
    {
        $steps = [
            ['key' => 'generated', 'label' => 'Orden registrada', 'description' => 'Pedido recibido por Quantum.'],
            ['key' => 'confirmed', 'label' => 'Revisión comercial', 'description' => 'Datos y disponibilidad en validación.'],
            ['key' => 'solicitado_proveedor', 'label' => 'Proveedor', 'description' => 'Partes solicitadas o en gestión de compra.'],
            ['key' => 'en_transito_aduanas', 'label' => 'Importación', 'description' => 'Tránsito internacional y proceso aduanero.'],
            ['key' => 'en_inventario_local', 'label' => 'Bodega', 'description' => 'Recepción y preparación local.'],
            ['key' => 'en_ruta', 'label' => 'Despacho', 'description' => 'Entrega coordinada al cliente.'],
            ['key' => 'entregado', 'label' => 'Entregado', 'description' => 'Pedido cerrado satisfactoriamente.'],
        ];

        $meta = $this->statusMeta($order);
        $currentIndex = $this->timelineIndex($meta['progress']);

        return collect($steps)->map(function ($step, $index) use ($currentIndex, $order, $meta) {
            $completed = $meta['key'] === 'cancelled' ? false : $index <= $currentIndex;
            $active = $meta['key'] !== 'cancelled' && $index === $currentIndex;

            return [
                ...$step,
                'completed' => $completed,
                'active' => $active,
                'date' => $completed ? ($order->updated_at ?: $order->created_at) : null,
            ];
        })->all();
    }

    private function timelineIndex(int $progress): int
    {
        return match (true) {
            $progress >= 100 => 6,
            $progress >= 85 => 5,
            $progress >= 72 => 4,
            $progress >= 52 => 3,
            $progress >= 35 => 2,
            $progress >= 20 => 1,
            default => 0,
        };
    }
}
