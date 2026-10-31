<?php

namespace App\Livewire\Storefront;

use App\Enums\TechnicalCategory;
use App\Models\Product;
use App\Services\Storefront\CartService;
use App\Services\Storefront\ProductService;
use App\Support\StorefrontUrl;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.storefront')]
class ProductList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = '';

    public string $brand = '';

    public string $categoria = '';

    public bool $inStock = false;

    public string $sort = 'name';

    protected $queryString = ['search' => ['except' => ''], 'type' => ['except' => ''], 'brand' => ['as' => 'marca', 'except' => ''], 'inStock' => ['as' => 'stock', 'except' => false], 'sort' => ['except' => 'name']];

    public function mount(?string $category = null): void
    {
        abort_if($category !== null && TechnicalCategory::tryFrom($category) === null, 404);
        $this->categoria = $category ?? '';
    }

    public function catalogFilters(): array
    {
        return array_filter(['search' => $this->search, 'marca' => $this->brand, 'stock' => $this->inStock ? 1 : '', 'type' => $this->type, 'sort' => $this->sort === 'name' ? '' : $this->sort], fn ($value) => $value !== '');
    }

    public function updated($property): void
    {
        if ($property === 'categoria') {
            if ($this->brand !== '' && ! app(ProductService::class)->brandCounts($this->search, $this->type, $this->categoria, $this->inStock)->keys()->contains(fn ($name) => mb_strtoupper(trim($name)) === mb_strtoupper(trim($this->brand)))) {
                $this->brand = '';
            }
            $this->redirect(StorefrontUrl::catalog($this->categoria, $this->catalogFilters()));
        }
        if (in_array($property, ['search', 'type', 'brand', 'categoria', 'inStock', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function add(int $productId, CartService $cart): void
    {
        $cart->add(Product::query()->findOrFail($productId));
        $this->dispatch('storefront-cart-added', count: $cart->count());
    }

    public function clearFilters(): void
    {
        $hadCategory = $this->categoria !== '';
        $this->reset('search', 'type', 'brand', 'categoria', 'inStock');
        $this->resetPage();
        if ($hadCategory) {
            $this->redirect(StorefrontUrl::catalog(null, $this->catalogFilters()));
        }
    }

    public function render(ProductService $products)
    {
        $catalogProducts = $products->paginate($this->search, $this->type, $this->brand, $this->categoria, 24, $this->inStock, $this->sort)
            ->withPath(StorefrontUrl::catalog($this->categoria))->appends($this->catalogFilters());

        return view('storefront.products.index', [
            'products' => $catalogProducts,
            ...$products->filterOptions($this->search, $this->type, $this->brand, $this->categoria, $this->inStock),
        ])->title(($this->categoria ? TechnicalCategory::from($this->categoria)->label() : 'Repuestos y componentes').' | Quantum Soluciones')
            ->layoutData([
                'canonicalUrl' => StorefrontUrl::catalog($this->categoria, $this->getPage() > 1 ? ['page' => $this->getPage()] : []),
                'seoDescription' => $this->categoria ? 'Consulta repuestos de '.TechnicalCategory::from($this->categoria)->label().' en el catálogo de Quantum Soluciones.' : 'Explora repuestos y componentes por categoría, marca o número de parte en Quantum Soluciones.',
            ]);
    }
}
