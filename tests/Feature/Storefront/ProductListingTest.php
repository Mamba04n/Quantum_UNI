<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\ProductList;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_filters_products_and_adds_one_to_cart(): void
    {
        $product = Product::factory()->create(['name' => 'Filtro hidráulico especial']);
        Product::factory()->create(['name' => 'Correa alternador']);

        Livewire::test(ProductList::class)
            ->set('search', 'hidráulico')
            ->assertSee($product->name)
            ->assertDontSee('Correa alternador')
            ->call('add', $product->id);

        $this->assertSame(1, session('storefront.cart')[$product->id]);
    }
}
