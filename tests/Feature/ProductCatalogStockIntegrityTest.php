<?php

namespace Tests\Feature;

use App\Livewire\Inventario\ProductManager;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogStockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_catalog_cannot_create_stock_without_kardex(): void
    {
        $user = $this->warehouseProductManager();

        Livewire::actingAs($user)
            ->test(ProductManager::class)
            ->call('create')
            ->set('name', 'Producto sin movimiento')
            ->set('stock_on_hand', 25)
            ->set('cost', 10)
            ->call('save')
            ->assertHasNoErrors();

        $product = Product::query()->where('name', 'Producto sin movimiento')->firstOrFail();

        $this->assertSame(0, $product->stock_on_hand);
        $this->assertSame('0.00', $product->total_cost);
        $this->assertDatabaseCount('stock_moves', 0);
    }

    public function test_product_catalog_cannot_change_existing_stock(): void
    {
        $user = $this->warehouseProductManager();
        $product = Product::factory()->create([
            'stock_on_hand' => 8,
            'cost' => 5,
            'total_cost' => 40,
        ]);

        Livewire::actingAs($user)
            ->test(ProductManager::class)
            ->call('edit', $product->id)
            ->set('stock_on_hand', 99)
            ->set('cost', 6)
            ->call('save')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertSame(8, $product->stock_on_hand);
        $this->assertSame('48.00', $product->total_cost);
        $this->assertDatabaseCount('stock_moves', 0);
    }

    private function warehouseProductManager(): User
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'warehouse.products.manage'],
            ['name' => 'Gestionar productos', 'module' => 'warehouse']
        );
        $role = Role::firstOrCreate(
            ['slug' => 'product-catalog-stock-test'],
            ['name' => 'Product catalog stock test']
        );
        $role->permissions()->sync([$permission->id]);

        $user = User::factory()->create(['role' => User::ROLE_BODEGA]);
        $user->roles()->sync([$role->id]);

        return $user;
    }
}
