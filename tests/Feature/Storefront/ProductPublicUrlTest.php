<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\ProductList;
use App\Models\Product;
use App\Support\StorefrontUrl;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class ProductPublicUrlTest extends TestCase
{
    use DatabaseTransactions;

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['active' => true, 'name' => 'Filtro de aceite de motor', 'part_number' => '1R-1808', 'brand' => 'Fleetguard', 'technical_category' => 'filtracion'], $attributes));
    }

    public function test_slug_is_descriptive_without_changing_the_id_binding(): void
    {
        $product = $this->product();
        $this->assertSame('filtro-aceite-motor-1r-1808', StorefrontUrl::productSlug($product));
        $this->assertSame('id', $product->getRouteKeyName());
        $this->assertStringEndsWith('/productos/'.$product->id.'/filtro-aceite-motor-1r-1808', StorefrontUrl::product($product));
        $product->name = 'Fíltro 1R-1808';
        $this->assertSame('filtro-1r-1808', StorefrontUrl::productSlug($product));
        $product->part_number = null;
        $product->name = 'Sensor de presión';
        $this->assertSame('sensor-presion', StorefrontUrl::productSlug($product));
    }

    public function test_old_wrong_and_outdated_slugs_redirect_permanently(): void
    {
        $product = $this->product();
        $canonical = StorefrontUrl::product($product);
        $this->get('/productos/'.$product->id)->assertStatus(301)->assertRedirect($canonical);
        $this->get('/productos/'.$product->id.'/otra-pieza')->assertStatus(301)->assertRedirect($canonical);
        $product->update(['name' => 'Filtro renovado']);
        $this->get($canonical)->assertStatus(301)->assertRedirect(StorefrontUrl::product($product));
    }

    public function test_missing_and_inactive_products_are_not_public(): void
    {
        $product = $this->product(['active' => false]);
        $this->get('/productos/'.$product->id)->assertNotFound();
        $this->get(StorefrontUrl::product($product))->assertNotFound();
        $this->get('/productos/999999999/inexistente')->assertNotFound();
        $this->get('/productos/categoria-inventada')->assertNotFound();
    }

    public function test_product_metadata_and_breadcrumb_use_canonical_urls(): void
    {
        $product = $this->product(['description' => 'Referencia real para revisión técnica.']);
        $url = StorefrontUrl::product($product);
        $this->get($url)->assertOk()
            ->assertSee('<title>Filtro de aceite de motor 1R-1808 | Quantum Soluciones</title>', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false)
            ->assertSee('<meta property="og:url" content="'.$url.'">', false)
            ->assertSee('Referencia real para revisión técnica.')
            ->assertSee('href="'.StorefrontUrl::catalog('filtracion').'"', false);
    }

    public function test_category_urls_filter_products_and_normalize_legacy_queries(): void
    {
        $match = $this->product(['name' => 'URLAUDIT filtro']);
        $other = $this->product(['name' => 'URLAUDIT sensor', 'technical_category' => 'electrico']);
        $this->get('/productos/filtracion?search=URLAUDIT&marca=Fleetguard&stock=1')
            ->assertOk()->assertSee($match->name)->assertDontSee($other->name)
            ->assertSee('<link rel="canonical" href="'.StorefrontUrl::catalog('filtracion').'">', false)
            ->assertSee('href="'.StorefrontUrl::product($match).'"', false);
        $this->get('/productos?categoria=filtracion&brand=Fleetguard&inStock=1')
            ->assertStatus(301)->assertRedirect(StorefrontUrl::catalog('filtracion', ['marca' => 'Fleetguard', 'stock' => '1']));
        $this->get('/productos?categoria=inventada')->assertNotFound();
        $this->get('/productos?categoria=')->assertStatus(301)->assertRedirect(StorefrontUrl::catalog());
    }

    public function test_live_filters_change_category_path_and_clear_without_stale_category(): void
    {
        $this->product(['brand' => 'Donaldson', 'technical_category' => 'electrico']);
        \App\Models\Brand::updateOrCreate(['name' => 'Donaldson'], ['slug' => 'donaldson', 'is_active' => true]);
        Livewire::test(ProductList::class, ['category' => 'filtracion'])
            ->set('brand', 'Donaldson')->set('categoria', 'electrico')
            ->assertRedirect(StorefrontUrl::catalog('electrico', ['marca' => 'Donaldson']));
        Livewire::test(ProductList::class, ['category' => 'filtracion'])
            ->call('clearFilters')->assertRedirect(StorefrontUrl::catalog());
    }

    public function test_cart_links_are_canonical_and_original_post_endpoint_still_works(): void
    {
        $product = $this->product();
        $this->post(route('storefront.products.cart.add', $product), ['quantity' => 2])
            ->assertRedirect(route('storefront.cart'));
        $this->get(route('storefront.cart'))->assertOk()
            ->assertSee('href="'.StorefrontUrl::product($product).'"', false);
    }

    public function test_pagination_keeps_category_and_secondary_filters(): void
    {
        Product::factory()->count(25)->create(['active' => true, 'name' => 'URLPAGINATION audit', 'brand' => 'URLPaginationBrand', 'technical_category' => 'filtracion']);
        $secondPage = StorefrontUrl::catalog('filtracion', ['search' => 'URLPAGINATION', 'marca' => 'URLPaginationBrand', 'page' => 2]);
        $this->get(StorefrontUrl::catalog('filtracion', ['search' => 'URLPAGINATION', 'marca' => 'URLPaginationBrand']))
            ->assertOk()->assertSee($secondPage);
        $this->get($secondPage)->assertOk()->assertSee('<link rel="canonical" href="'.StorefrontUrl::catalog('filtracion', ['page' => 2]).'">', false);
    }
}
