<?php

namespace App\Livewire\Ventas;

use App\Domain\Pedidos\Tracking\Services\PublicTrackingService;
use App\Models\OrderTracker;
use App\Models\PaymentLink;
use App\Models\SaleOrder;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PublicOrderCommerce extends Component
{
    #[Locked]
    public int $orderId;

    public string $estimatedDate = '';

    public function mount(int $orderId): void
    {
        abort_unless(auth()->user()->can('public_tracking.view') || auth()->user()->can('payment_links.view'), 403);
        $this->orderId = $orderId;
    }

    public function generate(PublicTrackingService $service): void
    {
        $this->validate(['estimatedDate' => 'nullable|date|after_or_equal:today']);
        $service->generate(auth()->user(), SaleOrder::findOrFail($this->orderId), $this->estimatedDate ?: null);
    }

    public function advance(string $expectedStatus, PublicTrackingService $service): void
    {
        $tracker = OrderTracker::where('public_sale_order_id', $this->orderId)->firstOrFail();
        $service->advance(auth()->user(), $tracker, $expectedStatus);
    }

    public function render()
    {
        abort_unless(auth()->user()->can('public_tracking.view') || auth()->user()->can('payment_links.view'), 403);

        return view('livewire.ventas.public-order-commerce', ['tracker' => OrderTracker::where('public_sale_order_id', $this->orderId)->first(), 'links' => auth()->user()->can('payment_links.view') ? PaymentLink::where('sale_order_id', $this->orderId)->latest('id')->limit(5)->get() : collect(), 'labels' => PublicTrackingService::LABELS]);
    }
}
