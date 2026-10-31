<?php

namespace App\Livewire\Ventas;

use App\Domain\Clientes\Services\CustomerService;
use App\Domain\Ventas\Pedidos\Services\DirectInventorySaleService;
use App\Models\Entity;
use App\Models\Product;
use App\Models\SaleOrder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class OrderForm extends Component
{
    public string $customerSearch = '';

    public string $productSearch = '';

    public string $customerId = '';

    public string $currency = 'NIO';

    public string $paymentType = 'Contado';

    public string $paymentMethod = 'Efectivo';

    public string $paymentReference = '';

    public string $dueDate = '';

    public string $notes = '';

    public bool $applyTax = true;

    public array $items = [];

    public string $idempotencyKey = '';

    public ?int $completedOrderId = null;

    public bool $showCustomerForm = false;

    public string $newCustomerName = '';

    public string $newCustomerRuc = '';

    public string $newCustomerContact = '';

    public string $newCustomerEmail = '';

    public string $newCustomerPhone = '';

    public string $newCustomerAddress = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('direct_order.create') || auth()->user()?->can('direct_order.create_from_inventory'), 403);
        $this->idempotencyKey = (string) Str::uuid();
        $this->dueDate = now()->addDays(30)->toDateString();
    }

    public function addProduct(int $productId): void
    {
        $product = Product::query()->whereKey($productId)->where('active', true)->firstOrFail();
        if ((int) $product->stock_on_hand <= 0 || collect($this->items)->contains(fn ($item) => (int) $item['product_id'] === $product->id)) {
            return;
        }
        $this->items[] = ['product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name, 'available' => (int) $product->stock_on_hand, 'quantity' => 1, 'unit_price' => number_format((float) $product->sales_price, 2, '.', '')];
        $this->productSearch = '';
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function openCustomerForm(): void
    {
        $this->resetCustomerForm();
        $this->showCustomerForm = true;
    }

    public function closeCustomerForm(): void
    {
        $this->showCustomerForm = false;
        $this->resetCustomerForm();
    }

    public function saveCustomer(CustomerService $customers): void
    {
        abort_unless(auth()->user()?->can('direct_order.create') || auth()->user()?->can('direct_order.create_from_inventory'), 403);

        $data = $this->validate([
            'newCustomerName' => ['required', 'string', 'max:255'],
            'newCustomerRuc' => ['nullable', 'string', 'max:50'],
            'newCustomerContact' => ['nullable', 'string', 'max:255'],
            'newCustomerEmail' => ['nullable', 'email', 'max:255'],
            'newCustomerPhone' => ['nullable', 'string', 'max:20'],
            'newCustomerAddress' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'newCustomerName' => 'nombre o razón social',
            'newCustomerRuc' => 'RUC',
            'newCustomerContact' => 'persona de contacto',
            'newCustomerEmail' => 'correo',
            'newCustomerPhone' => 'teléfono',
            'newCustomerAddress' => 'dirección',
        ]);

        $customer = $customers->createFromAdmin([
            'name' => trim($data['newCustomerName']),
            'rfc_ruc' => filled($data['newCustomerRuc']) ? trim($data['newCustomerRuc']) : null,
            'contact_info' => filled($data['newCustomerContact']) ? trim($data['newCustomerContact']) : null,
            'email' => filled($data['newCustomerEmail']) ? mb_strtolower(trim($data['newCustomerEmail'])) : null,
            'phone_mobile' => filled($data['newCustomerPhone']) ? trim($data['newCustomerPhone']) : null,
            'exact_address' => filled($data['newCustomerAddress']) ? trim($data['newCustomerAddress']) : null,
            'country' => 'Nicaragua',
        ]);

        $this->customerId = (string) $customer->id;
        $this->customerSearch = $customer->name;
        $this->showCustomerForm = false;
        $this->resetCustomerForm();
        session()->flash('customer_message', "Cliente {$customer->name} agregado y seleccionado.");
    }

    public function confirm(DirectInventorySaleService $service): void
    {
        abort_unless(auth()->user()?->can('direct_order.create') || auth()->user()?->can('direct_order.create_from_inventory'), 403);
        $validated = $this->validate([
            'customerId' => ['required', Rule::exists('entities', 'id')->where('type', 'cliente')],
            'currency' => ['required', Rule::in(['NIO', 'USD'])],
            'paymentType' => ['required', Rule::in(['Contado', 'Crédito'])],
            'paymentMethod' => ['required_if:paymentType,Contado', Rule::in(['Efectivo', 'Transferencia', 'Tarjeta', 'Cheque'])],
            'paymentReference' => ['nullable', 'string', 'max:120'],
            'dueDate' => ['required_if:paymentType,Crédito', 'nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'], 'applyTax' => ['boolean'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'idempotencyKey' => ['required', 'uuid'],
        ], [], ['customerId' => 'cliente', 'paymentType' => 'condición de pago', 'paymentMethod' => 'método de pago', 'dueDate' => 'fecha de vencimiento', 'items' => 'productos', 'items.*.quantity' => 'cantidad', 'items.*.unit_price' => 'precio unitario']);

        $order = $service->create([
            'customer_id' => (int) $validated['customerId'], 'currency' => $validated['currency'],
            'payment_type' => $validated['paymentType'], 'payment_method' => $validated['paymentMethod'],
            'payment_reference' => $validated['paymentReference'] ?: null,
            'due_date' => $validated['paymentType'] === 'Crédito' ? $validated['dueDate'] : now()->toDateString(),
            'notes' => $validated['notes'] ?: null, 'apply_tax' => $validated['applyTax'],
            'items' => $validated['items'], 'idempotency_key' => $validated['idempotencyKey'],
        ], auth()->user());
        $this->completedOrderId = $order->id;
        $this->idempotencyKey = (string) Str::uuid();
        session()->flash('message', "Venta {$order->reference} registrada. Se generaron factura, CXC y salida de Kardex.");
    }

    public function getSubtotalProperty(): float
    {
        return round(collect($this->items)->sum(fn ($item) => (int) ($item['quantity'] ?? 0) * (float) ($item['unit_price'] ?? 0)), 2);
    }

    public function getTaxAmountProperty(): float
    {
        return $this->applyTax ? round($this->subtotal * 0.15, 2) : 0;
    }

    public function getTotalProperty(): float
    {
        return round($this->subtotal + $this->taxAmount, 2);
    }

    public function render()
    {
        $customers = Entity::query()->where('type', 'cliente')->when(trim($this->customerSearch) !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$this->customerSearch.'%')->orWhere('legal_name', 'like', '%'.$this->customerSearch.'%')->orWhere('rfc_ruc', 'like', '%'.$this->customerSearch.'%')))->orderBy('name')->limit(30)->get();
        $products = Product::query()->where('active', true)->where('stock_on_hand', '>', 0)->when(trim($this->productSearch) !== '', fn ($query) => $query->where(fn ($q) => $q->where('name', 'like', '%'.$this->productSearch.'%')->orWhere('sku', 'like', '%'.$this->productSearch.'%')->orWhere('part_number', 'like', '%'.$this->productSearch.'%')))->orderBy('name')->limit(20)->get();
        $completedOrder = $this->completedOrderId ? SaleOrder::with('invoices')->find($this->completedOrderId) : null;

        return view('livewire.ventas.order-form', compact('customers', 'products', 'completedOrder'));
    }

    private function resetCustomerForm(): void
    {
        $this->reset([
            'newCustomerName', 'newCustomerRuc', 'newCustomerContact',
            'newCustomerEmail', 'newCustomerPhone', 'newCustomerAddress',
        ]);
        $this->resetValidation([
            'newCustomerName', 'newCustomerRuc', 'newCustomerContact',
            'newCustomerEmail', 'newCustomerPhone', 'newCustomerAddress', 'customer',
        ]);
    }
}
