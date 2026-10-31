<?php

namespace App\Livewire\Finanzas;

use App\Domain\Finanzas\PaymentLinks\PaymentLinkImages;
use App\Domain\Finanzas\PaymentLinks\PaymentLinkService;
use App\Domain\Finanzas\ValueObjects\DecimalMoney as Money;
use App\Models\AccountReceivable;
use App\Models\Entity;
use App\Models\PaymentLink;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Services\ExchangeRateService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class PaymentLinkManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $customerSearch = '';

    public string $productSearch = '';

    public bool $creating = false;

    #[Url(as: 'products')]
    public string $prefillProducts = '';

    #[Url(as: 'order')]
    public ?int $orderId = null;

    public ?int $customerId = null;

    public ?int $receivableId = null;

    public string $description = 'Pedido de repuestos';

    public string $internalNote = '';

    public string $rate = '';

    public string $expiresAt = '';

    public array $items = [];

    public ?int $selectedLinkId = null;

    #[Locked]
    public string $issuanceKey = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('payment_links.view'), 403);
        $this->expiresAt = now()->addHours(72)->format('Y-m-d\TH:i');
        try {
            $this->rate = number_format((float) app(ExchangeRateService::class)->rateToNio('USD')['rate'], 6, '.', '');
        } catch (\Throwable) {
            $this->rate = '';
        }
        if ($this->orderId) {
            $this->newLink();
        } elseif ($this->prefillProducts && auth()->user()->can('payment_links.create')) {
            $this->newLink();
            foreach (array_slice(array_unique(explode(',', $this->prefillProducts)), 0, 50) as $id) {
                if (ctype_digit($id) && Product::where('active', true)->whereKey($id)->exists()) {
                    $this->addProduct((int) $id);
                }
            }
        }
    }

    public function newLink(): void
    {
        abort_unless(auth()->user()->can('payment_links.create'), 403);
        $this->creating = true;
        $this->resetValidation();
        $this->issuanceKey = (string) Str::uuid();
        if ($this->orderId) {
            $order = SaleOrder::with('items.product')->findOrFail($this->orderId);
            $this->customerId = $order->customer_id;
            $this->receivableId = $order->accountReceivable?->id;
            $this->description = 'Cobro de orden '.$order->reference;
            $this->items = $order->items->map(fn ($i) => ['product_id' => $i->product_id, 'part_number' => $i->product?->part_number ?: $i->product?->sku, 'description' => $i->product?->name ?: 'Partida de orden', 'quantity' => (int) $i->quantity, 'unit_price' => $order->currency === 'USD' ? $i->unit_price : ($this->rate ? Money::convertFromBase($i->unit_price, $this->rate) : '0.00')])->all();
        }
    }

    public function addProduct(int $id): void
    {
        abort_unless(auth()->user()->can('payment_links.create'), 403);
        if (count($this->items) >= 50) {
            return;
        }
        $p = Product::where('active', true)->findOrFail($id);
        foreach ($this->items as $index => $item) {
            if ($item['product_id'] === $p->id) {
                $this->items[$index]['quantity'] = min(10000, (int) $item['quantity'] + 1);

                return;
            }
        }
        $this->items[] = ['product_id' => $p->id, 'image_url' => app(PaymentLinkImages::class)->preview($p), 'part_number' => $p->part_number ?: $p->sku, 'description' => $p->description ?: $p->name, 'quantity' => 1, 'unit_price' => $this->rate ? Money::convertFromBase($p->sales_price ?? '0', $this->rate) : '0.00'];
        $this->productSearch = '';
    }

    public function selectCustomer(int $id): void
    {
        abort_unless(auth()->user()->can('payment_links.create'), 403);
        $this->customerId = Entity::where('type', 'cliente')->findOrFail($id)->id;
        $this->customerSearch = '';
        $this->orderId = null;
        $this->receivableId = null;
    }

    public function addConcept(): void
    {
        abort_unless(auth()->user()->can('payment_links.override_price'), 403);
        if (count($this->items) < 50) {
            $this->items[] = ['product_id' => null, 'part_number' => null, 'description' => '', 'quantity' => 1, 'unit_price' => '0.00'];
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function createLink(PaymentLinkService $service): void
    {
        $link = $service->create(auth()->user(), ['issuance_key' => $this->issuanceKey, 'customer_id' => $this->customerId, 'sale_order_id' => $this->orderId, 'account_receivable_id' => $this->receivableId, 'description' => $this->description, 'internal_note' => $this->internalNote, 'expires_at' => $this->expiresAt, 'items' => $this->items]);
        $this->selectedLinkId = $link->id;
        $this->creating = false;
        $this->items = [];
        session()->flash('message', 'Enlace de pago generado.');
    }

    public function cancelLink(int $id, PaymentLinkService $service): void
    {
        $service->cancel(auth()->user(), PaymentLink::findOrFail($id));
    }

    public function reconcileLink(int $id, PaymentLinkService $service): void
    {
        try {
            $service->reconcile(auth()->user(), PaymentLink::findOrFail($id));
            session()->flash('message', 'Estado consultado con PayPal. Revisa la aplicación financiera del cobro.');
        } catch (\RuntimeException) {
            $this->addError('payment', 'No se pudo consultar PayPal. Intenta nuevamente.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        abort_unless(auth()->user()->can('payment_links.view'), 403);
        $query = PaymentLink::with(['customer', 'order'])->when($this->search, fn ($q) => $q->where('code', 'like', '%'.$this->search.'%'));
        if ($this->status === 'expired') {
            $query->where('status', 'pending')->where('expires_at', '<=', now());
        } elseif ($this->status === 'pending') {
            $query->where('status', 'pending')->where('expires_at', '>', now());
        } elseif ($this->status) {
            $query->where('status', $this->status);
        }
        if ($this->orderId) {
            $query->where('sale_order_id', $this->orderId);
        }

        return view('livewire.finanzas.payment-link-manager', [
            'links' => $query->latest('id')->paginate(15),
            'customers' => $this->customerSearch !== '' ? Entity::where('type', 'cliente')->where('name', 'like', '%'.$this->customerSearch.'%')->limit(10)->get(['id', 'name']) : collect(),
            'customer' => $this->customerId ? Entity::find($this->customerId) : null,
            'products' => $this->creating ? Product::where('active', true)->when($this->productSearch !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', '%'.$this->productSearch.'%')->orWhere('part_number', 'like', '%'.$this->productSearch.'%')->orWhere('sku', 'like', '%'.$this->productSearch.'%')), fn ($q) => $q->where('stock_on_hand', '>', 0))->orderBy('name')->limit(12)->get() : collect(),
            'orders' => $this->customerId ? SaleOrder::where('customer_id', $this->customerId)->latest('id')->limit(50)->get(['id', 'reference']) : collect(),
            'accounts' => $this->customerId ? AccountReceivable::where('entity_id', $this->customerId)->whereColumn('paid_amount', '<', 'total_amount')->limit(50)->get() : collect(),
            'selected' => $this->selectedLinkId ? PaymentLink::with(['customer', 'order', 'items'])->find($this->selectedLinkId) : null,
        ]);
    }
}
