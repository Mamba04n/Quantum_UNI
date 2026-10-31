<?php

use App\Enums\TechnicalCategory;
use App\Http\Controllers\AdjuntosPrivadosController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Storefront\BlogController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\ServiceController;
use App\Http\Middleware\CanonicalCatalogUrl;
use App\Livewire\Clientes\Portal;
use App\Livewire\Storefront\Cart;
use App\Livewire\Storefront\ContactForm;
use App\Livewire\Storefront\ProductList;
use Illuminate\Support\Facades\Route;

Route::name('storefront.')->group(function (): void {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/nosotros', [HomeController::class, 'about'])->name('about');
    Route::get('/productos', ProductList::class)->middleware(CanonicalCatalogUrl::class)->name('products.index');
    Route::get('/productos/{product}/{slug?}', [ProductController::class, 'show'])->whereNumber('product')->name('products.show');
    Route::post('/productos/{product}/carrito', [ProductController::class, 'addToCart'])->whereNumber('product')->name('products.cart.add');
    Route::get('/productos/{category}', ProductList::class)->whereIn('category', TechnicalCategory::values())->middleware(CanonicalCatalogUrl::class)->name('products.category');
    Route::get('/catalogos', [CatalogController::class, 'index'])->name('catalogs.index');
    Route::get('/catalogos/descargar/{file}', [CatalogController::class, 'download'])->name('catalogs.download');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/servicios', [ServiceController::class, 'index'])->name('services.index');
    Route::get('/contacto', ContactForm::class)->name('contact');
    Route::get('/carrito', Cart::class)->name('cart');
    Route::view('/marcas-atendidas', 'storefront.modules.brands')->name('modules.brands');
    Route::view('/como-trabajamos', 'storefront.modules.methodology')->name('modules.methodology');
    Route::view('/identificar-pieza', 'storefront.modules.identify')->name('modules.identify');
    Route::view('/guias-de-mantenimiento', 'storefront.modules.guides')->name('modules.guides');
    Route::view('/mantenimiento-preventivo', 'storefront.modules.maintenance')->name('modules.maintenance');
    Route::view('/diagnostico-tecnico', 'storefront.modules.diagnostics')->name('modules.diagnostics');
    Route::view('/soporte-en-campo', 'storefront.modules.field-support')->name('modules.field-support');
    Route::view('/fichas-tecnicas', 'storefront.modules.technical-sheets')->name('modules.technical-sheets');
    Route::view('/terminos-y-condiciones', 'storefront.legal.terms')->name('legal.terms');
    Route::view('/politica-de-privacidad', 'storefront.legal.privacy')->name('legal.privacy');
    Route::view('/politica-de-cotizaciones-y-garantias', 'storefront.legal.quotes')->name('legal.quotes');
    Route::view('/politica-de-cookies', 'storefront.legal.cookies')->name('legal.cookies');
});

Route::get('/mi-portal', Portal::class)->middleware(['auth', 'verified'])->name('customer.portal');
Route::get('/cotizar-con-mi-cuenta', fn () => redirect()->route('storefront.cart'))
    ->middleware(['auth', 'verified'])->name('customer.quote');
Route::get('/cliente/tickets', fn () => redirect()->route('customer.portal', ['section' => 'tickets']))
    ->middleware(['auth', 'verified'])->name('customer.tickets');
Route::get('/mi-portal/documentos/{document}', [AdjuntosPrivadosController::class, 'customerOrderStatusDocument'])
    ->middleware(['auth', 'verified', 'no-cache'])->name('customer.portal.documents.download');

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/logout', fn () => abort(405))->middleware('auth')->name('logout.get');
