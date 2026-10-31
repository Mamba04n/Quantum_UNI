<?php

use App\Livewire\Bodega\IncomingOrders;
use App\Livewire\Inventario\ProductManager;
use App\Livewire\Inventario\StockMovement;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache', 'permission:warehouse.inventory.view,warehouse.products.manage'])->group(function (): void {
    Route::get('/inventory/products', ProductManager::class)->name('inventory.products');
    Route::get('/inventory/moves', StockMovement::class)->name('inventory.moves');
    Route::get('/warehouse/incoming-orders', IncomingOrders::class)
        ->middleware('permission:incoming_orders.view,warehouse.inventory.view')
        ->name('warehouse.incoming-orders');
});
