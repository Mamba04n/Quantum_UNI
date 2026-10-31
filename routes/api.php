<?php

use App\Application\Http\Controllers\ClienteController;
use App\Http\Controllers\Api\V1\Storefront\CartController;
use App\Http\Controllers\Api\V1\Storefront\CatalogController;
use App\Http\Controllers\Api\V1\Storefront\CategoryController;
use App\Http\Controllers\Api\V1\Storefront\ContactController;
use App\Http\Controllers\Api\V1\Storefront\ProductController;
use App\Http\Controllers\Api\V1\Storefront\ProductSearchController;
use App\Http\Controllers\Api\V1\Storefront\ServiceRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/clients', [ClienteController::class, 'store'])
        ->middleware('permission:crm.clients.manage')
        ->name('api.legacy.clients.store');

    // Route::post('/machines', [MaquinaController::class, 'store'])
    // ->middleware('permission:customer_request.create,operations.view');

    // Route::get('/machines/{machine}', [MaquinaController::class, 'show'])
    // ->middleware('permission:customer_request.view,operations.view');

});

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:storefront')->prefix('storefront')->name('storefront.')->group(function (): void {
        Route::get('/products/search', ProductSearchController::class)->name('products.search');
        Route::get('/products/featured', [ProductController::class, 'featured'])->name('products.featured');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/catalogs', [CatalogController::class, 'index'])->name('catalogs.index');
        Route::get('/catalogs/download/{file}', [CatalogController::class, 'download'])
            ->where('file', '.*')
            ->name('catalogs.download');
        Route::post('/cart/details', [CartController::class, 'details'])->name('cart.details');
        Route::post('/quotes', [CartController::class, 'quote'])->name('quotes.store');
        Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');
        Route::post('/services', [ServiceRequestController::class, 'store'])->name('services.store');
    });

    Route::middleware('auth:sanctum')->prefix('erp')->name('erp.')->group(function (): void {
        Route::post('/clients', [ClienteController::class, 'store'])
            ->middleware('permission:crm.clients.manage')
            ->name('clients.store');

        // Route::post('/machines', [MaquinaController::class, 'store'])
        // ->middleware('permission:customer_request.create,operations.view');

        // Route::get('/machines/{machine}', [MaquinaController::class, 'show'])
        // ->middleware('permission:customer_request.view,operations.view');

    });

});

require __DIR__.'/api_finance.php';

require __DIR__.'/api_integration.php';
