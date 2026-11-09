<?php

use App\Http\Controllers\Api\V1\Integration\IdentityController;
use App\Http\Controllers\Api\V1\Integration\ItemController;
use App\Http\Controllers\Api\V1\Integration\MasterController;
use App\Http\Controllers\Api\V1\Integration\PurchaseRequestController;
use App\Http\Controllers\Api\V1\Integration\ReservationController;
use App\Http\Controllers\Api\V1\Integration\StockController;
use App\Http\Middleware\AuthenticateIntegration;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/integration')->name('api.v1.integration.')->group(function (): void {
    Route::get('/capabilities', [IdentityController::class, 'capabilities'])
        ->middleware(AuthenticateIntegration::class.':integration:read')->name('capabilities');
    Route::get('/users/{id}', [IdentityController::class, 'show'])
        ->whereNumber('id')->middleware(AuthenticateIntegration::class.':users:read')->name('users.show');
    foreach (['customers', 'assets', 'employees'] as $resource) {
        Route::get('/'.$resource, [MasterController::class, 'index'])->defaults('resource', $resource)
            ->middleware(AuthenticateIntegration::class.':'.$resource.':read')->name($resource.'.index');
        if ($resource !== 'employees') {
            Route::get('/'.$resource.'/{id}', [MasterController::class, 'show'])->whereNumber('id')->defaults('resource', $resource)
                ->middleware(AuthenticateIntegration::class.':'.$resource.':read')->name($resource.'.show');
        }
    }
    Route::get('/customers/{customer}/assets', [MasterController::class, 'index'])->whereNumber('customer')->defaults('resource', 'assets')
        ->middleware(AuthenticateIntegration::class.':assets:read')->name('customers.assets');

    // Catálogo de Items / Repuestos Whitelist DTO
    Route::get('/items', [ItemController::class, 'index'])
        ->middleware(AuthenticateIntegration::class.':integration:items:read')->name('items.index');
    Route::get('/items/{id}', [ItemController::class, 'show'])
        ->whereNumber('id')->middleware(AuthenticateIntegration::class.':integration:items:read')->name('items.show');

    // Disponibilidad Real de Stock por Bodega
    Route::get('/items/{id}/stock', [StockController::class, 'show'])
        ->whereNumber('id')->middleware(AuthenticateIntegration::class.':integration:stock:read')->name('items.stock');

    // Reservas de Inventario Atómicas e Idempotentes
    Route::post('/inventory/reservations', [ReservationController::class, 'store'])
        ->middleware(AuthenticateIntegration::class.':integration:inventory:reserve')->name('inventory.reservations.store');
    Route::post('/inventory/reservations/{id}/release', [ReservationController::class, 'release'])
        ->whereNumber('id')->middleware(AuthenticateIntegration::class.':integration:inventory:release')->name('inventory.reservations.release');
    Route::post('/inventory/reservations/{id}/consume', [ReservationController::class, 'consume'])
        ->whereNumber('id')->middleware(AuthenticateIntegration::class.':integration:inventory:consume')->name('inventory.reservations.consume');

    // Solicitudes de Compra CMMS
    Route::post('/purchase-requests', [PurchaseRequestController::class, 'store'])
        ->middleware(AuthenticateIntegration::class.':integration:purchase-requests:create')->name('purchase_requests.store');
    Route::get('/purchase-requests/{id}', [PurchaseRequestController::class, 'show'])
        ->middleware(AuthenticateIntegration::class.':integration:purchase-requests:read')->name('purchase_requests.show');
});
