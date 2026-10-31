<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\SaleOrder;
use App\Models\StockMove;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AcademicInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_seed_is_repeatable_and_install_command_rejects_testing(): void
    {
        Storage::fake('private');
        config(['quantum_uni.admin_password' => 'AcademicTestOnly#2026']);
        $this->seed(DatabaseSeeder::class);
        $product = Product::where('part_number', 'E2-FILTRO-ACADEMICO')->firstOrFail();
        $this->assertSame(6, (int) $product->stock_on_hand);
        $this->assertStringStartsWith('QS-CAS-OTR-', $product->sku);
        $this->assertEquals(600, $product->total_cost);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, Product::count());
        $this->assertSame(1, PurchaseOrder::count());
        $this->assertSame(1, PurchaseReceipt::count());
        $this->assertSame(1, SaleOrder::count());
        $this->assertSame(2, StockMove::count());
        $this->assertSame(6, (int) $product->fresh()->stock_on_hand);
        $this->artisan('quantum-uni:install')->assertFailed();
    }
}
