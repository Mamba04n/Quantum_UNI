<?php

namespace App\Livewire\Storefront;

use App\Http\Requests\Storefront\QuoteRequest;
use App\Services\Storefront\CartQuoteSummary;
use App\Services\Storefront\CartService;
use App\Services\Storefront\QuoteService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.storefront')]
class Cart extends Component
{
    public array $quantities = [];

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $contact_name = '';

    public string $company = '';

    public string $ruc = '';

    public string $machinery = '';

    public string $machine_brand = '';

    public string $machine_model = '';

    public string $equipment_serial = '';

    public string $urgency = 'media';

    public string $notes = '';

    public string $password = '';

    public ?array $submittedQuote = null;

    public function mount(CartService $cart): void
    {
        $this->quantities = $cart->quantities();
        if (auth()->check()) {
            $this->name = (string) auth()->user()->name;
            $this->email = (string) auth()->user()->email;
            $this->phone = (string) auth()->user()->phone;
            $this->contact_name = (string) auth()->user()->name;
        }
    }

    public function updateQuantity(int $productId, CartService $cart): void
    {
        $this->validate(["quantities.{$productId}" => ['required', 'integer', 'min:1', 'max:999']]);
        $cart->update($productId, (int) $this->quantities[$productId]);
        session()->flash('success', 'Cantidad actualizada.');
        $this->dispatch('storefront-cart-count', count: $cart->count());
    }

    public function changeQuantity(int $productId, int $change, CartService $cart): void
    {
        $quantities = $cart->quantities();
        if (isset($quantities[$productId]) && in_array($change, [-1, 1], true)) {
            $cart->update($productId, $quantities[$productId] + $change);
            $this->quantities = $cart->quantities();
        }
        $this->dispatch('storefront-cart-count', count: $cart->count());
    }

    public function remove(int $productId, CartService $cart): void
    {
        $cart->remove($productId);
        unset($this->quantities[$productId]);
        $this->dispatch('storefront-cart-count', count: $cart->count());
    }

    public function clear(CartService $cart): void
    {
        $cart->clear();
        $this->quantities = [];
        $this->dispatch('storefront-cart-count', count: 0);
    }

    public function requestQuote(QuoteService $quotes): void
    {
        $data = $this->validate((new QuoteRequest)->rules());
        $quote = $quotes->create($data);
        $this->quantities = [];
        $this->submittedQuote = $quote;
        $this->dispatch('storefront-cart-count', count: 0);
        session()->flash('success', "Solicitud {$quote['code']} registrada.");
    }

    public function render(CartService $cart, CartQuoteSummary $summary)
    {
        $items = $cart->items();

        return view('storefront.cart.index', [
            'items' => $items,
            'summary' => $summary->build($items),
            'subtotal' => $cart->subtotal(),
            'submittedQuote' => $this->submittedQuote,
        ])->title('Solicitud de cotización | Quantum Soluciones');
    }
}
