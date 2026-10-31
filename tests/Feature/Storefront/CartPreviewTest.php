<?php

namespace Tests\Feature\Storefront;

use App\Livewire\Storefront\CartPreview;
use App\Livewire\Storefront\ProductList;
use App\Models\Product;
use App\Services\Storefront\CartService;
use App\Services\Storefront\ProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CartPreviewTest extends TestCase
{
    use DatabaseTransactions;

    public function test_facets_only_offer_brands_and_categories_matching_other_filters(): void
    {
        \App\Models\Brand::create(['name' => 'FacetAudit FilterBrand', 'slug' => 'facetaudit-filterbrand', 'is_active' => true]);
        \App\Models\Brand::create(['name' => 'FacetAudit MechBrand', 'slug' => 'facetaudit-mechbrand', 'is_active' => true]);
        Product::factory()->create(['active' => true, 'name' => 'FacetScope oil', 'brand' => 'FacetAudit FilterBrand', 'technical_category' => 'filtracion', 'stock_on_hand' => 3]);
        Product::factory()->create(['active' => true, 'name' => 'FacetScope gear', 'brand' => 'FacetAudit MechBrand', 'technical_category' => 'mecanico', 'stock_on_hand' => 0]);
        $service = app(ProductService::class);
        $mechanical = $service->filterOptions('FacetScope', '', '', 'mecanico');
        $this->assertSame(['FacetAudit MechBrand'], $mechanical['brands']->all());
        $filtered = $service->filterOptions('FacetScope', '', 'FacetAudit FilterBrand');
        $this->assertSame(['filtracion'], $filtered['categories']->pluck('value')->all());
        $stock = $service->filterOptions('FacetScope', '', '', '', true);
        $this->assertSame(['FacetAudit FilterBrand'], $stock['brands']->all());
        $this->assertSame(['filtracion'], $stock['categories']->pluck('value')->all());
        $searched = $service->filterOptions('FacetScope oil');
        $this->assertSame(['FacetAudit FilterBrand'], $searched['brands']->all());
        Livewire::test(\App\Livewire\Storefront\ProductList::class)
            ->set('search', 'FacetScope')->set('brand', 'FacetAudit FilterBrand')->set('categoria', 'mecanico')
            ->assertSet('brand', '');
    }

    public function test_brand_options_use_active_master_brands_with_published_products(): void
    {
        \App\Models\Brand::create(['name' => 'BrandAudit Valid', 'slug' => 'brand-audit-valid', 'is_active' => true]);
        \App\Models\Brand::create(['name' => 'BrandAudit Inactive', 'slug' => 'brand-audit-inactive', 'is_active' => false]);
        \App\Models\Brand::create(['name' => 'BrandAudit Empty', 'slug' => 'brand-audit-empty', 'is_active' => true]);
        Product::factory()->create(['active' => true, 'brand' => 'BrandAudit Valid']);
        Product::factory()->create(['active' => true, 'brand' => ' BRANDAUDIT VALID ']);
        Product::factory()->create(['active' => false, 'brand' => 'BrandAudit Valid']);
        Product::factory()->create(['active' => true, 'brand' => 'BrandAudit Unregistered']);
        Product::factory()->create(['active' => true, 'brand' => 'BrandAudit Inactive']);
        $service = app(ProductService::class);
        $counts = $service->brandCounts();
        $this->assertSame(2, $counts->get('BrandAudit Valid'));
        $this->assertFalse($counts->has('BrandAudit Inactive'));
        $this->assertFalse($counts->has('BrandAudit Empty'));
        $this->assertFalse($counts->has('BrandAudit Unregistered'));
        $this->assertSame(2, $service->paginate('', '', 'BrandAudit Valid')->total());
    }

    public function test_search_matches_brand_and_trims_whitespace_without_returning_inactive_products(): void
    {
        $product = Product::factory()->create(['active' => true, 'brand' => 'SearchBrandAuditUnique']);
        Product::factory()->create(['active' => false, 'brand' => 'SearchBrandAuditUnique']);
        $service = app(ProductService::class);
        $this->assertSame([$product->id], $service->paginate('  SearchBrandAuditUnique  ')->pluck('id')->all());
        $this->assertSame([$product->id], $service->search('  SearchBrandAuditUnique  ')->pluck('id')->all());
    }

    public function test_catalogue_stock_filter_and_sort_use_inventory_records(): void
    {
        $first = Product::factory()->create(['active' => true, 'name' => 'FacetAudit Alpha', 'stock_on_hand' => 4]);
        $last = Product::factory()->create(['active' => true, 'name' => 'FacetAudit Zulu', 'stock_on_hand' => 2]);
        Product::factory()->create(['active' => true, 'name' => 'FacetAudit Hidden', 'stock_on_hand' => 0]);
        $page = app(ProductService::class)->paginate('FacetAudit', '', '', '', 24, true, 'name_desc');
        $this->assertSame([$last->id, $first->id], $page->pluck('id')->all());
        Livewire::test(ProductList::class)->set('brand', 'Example')->set('inStock', true)->set('search', 'FacetAudit')
            ->call('clearFilters')->assertSet('brand', '')->assertSet('inStock', false)->assertSet('search', '');
    }

    public function test_catalogue_add_opens_preview_and_quantity_controls_preserve_cart(): void
    {
        $product = Product::factory()->create(['active' => true]);
        Livewire::test(ProductList::class)->call('add', $product->id)
            ->assertDispatched('storefront-cart-added', count: 1);
        $preview = Livewire::test(CartPreview::class)
            ->call('changeQuantity', $product->id, 1)
            ->assertDispatched('storefront-cart-count', count: 2);
        $this->assertSame(2, app(CartService::class)->quantities()[$product->id]);
        $preview->call('changeQuantity', $product->id, -1)->call('changeQuantity', $product->id, -1);
        $this->assertSame(1, app(CartService::class)->quantities()[$product->id]);
        $preview->call('remove', $product->id);
        $this->assertSame([], app(CartService::class)->quantities());
    }
}
