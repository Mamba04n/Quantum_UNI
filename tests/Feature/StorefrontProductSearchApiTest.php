<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_short_storefront_product_search_returns_standard_empty_response(): void
    {
        $this->getJson('/api/v1/storefront/products/search?q=f')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Search query is too short.',
                'data' => [],
                'errors' => [],
                'meta' => ['minimum_length' => 2],
            ]);
    }

    public function test_storefront_product_search_returns_active_products_with_standard_contract(): void
    {
        $partNumber = 'API-SEARCH-'.strtoupper(substr(md5((string) microtime(true)), 0, 8));

        Product::factory()->create([
            'name' => 'Filtro hidraulico premium',
            'part_number' => $partNumber,
            'active' => true,
        ]);

        Product::factory()->create([
            'name' => 'Filtro hidraulico inactivo',
            'part_number' => $partNumber.'-OFF',
            'active' => false,
        ]);

        $this->assertDatabaseHas('products', [
            'part_number' => $partNumber,
            'active' => true,
        ]);

        $this->getJson('/api/v1/storefront/products/search?'.http_build_query(['q' => $partNumber]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Products found.')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('data.0.name', 'Filtro hidraulico premium')
            ->assertJsonPath('data.0.part_number', $partNumber)
            ->assertJsonPath('data.0.active', true)
            ->assertJsonMissing(['name' => 'Filtro hidraulico inactivo']);
    }
}
