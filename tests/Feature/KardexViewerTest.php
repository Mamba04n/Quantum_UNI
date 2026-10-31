<?php

namespace Tests\Feature;

use App\Livewire\Administracion\Kardex\VisorKardex;
use App\Models\Product;
use App\Models\StockMove;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KardexViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_kardex_by_sku_and_type(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $entryProduct = Product::factory()->create([
            'name' => 'Entrada Kardex Producto',
            'sku' => 'KDX-IN-100',
            'active' => true,
        ]);
        $outputProduct = Product::factory()->create([
            'name' => 'Salida Kardex Producto',
            'sku' => 'KDX-100',
            'active' => true,
        ]);

        StockMove::create([
            'product_id' => $entryProduct->id,
            'user_id' => $admin->id,
            'movement_type' => 'in',
            'movement_date' => '2026-01-01',
            'quantity' => 5,
            'unit_cost' => 10,
            'reference' => 'IN-KDX-100',
        ]);

        StockMove::create([
            'product_id' => $outputProduct->id,
            'user_id' => $admin->id,
            'movement_type' => 'out',
            'movement_date' => '2026-01-02',
            'quantity' => -2,
            'unit_cost' => 10,
            'reference' => 'OUT-KDX-100',
        ]);

        $entrySku = $entryProduct->fresh()->sku;
        $outputSku = $outputProduct->fresh()->sku;

        Livewire::actingAs($admin)
            ->test(VisorKardex::class)
            ->assertSee($entrySku)
            ->assertSee($outputSku)
            ->set('filterSku', $outputSku)
            ->assertSee($outputSku)
            ->assertDontSee($entrySku)
            ->set('filterType', 'out')
            ->assertSee($outputSku)
            ->assertDontSee($entrySku);
    }
}
