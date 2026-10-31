<?php

use App\Http\Controllers\AuthenticatedLivewireFilePreviewController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/storefront.php';
require __DIR__.'/admin.php';
require __DIR__.'/crm.php';
require __DIR__.'/ventas.php';
require __DIR__.'/compras.php';
require __DIR__.'/inventario.php';
require __DIR__.'/finanzas.php';
require __DIR__.'/public-commerce.php';
require __DIR__.'/private.php';
require __DIR__.'/testing.php';
require __DIR__.'/auth.php';
require __DIR__.'/explorador.php';

Route::get('/livewire/preview-file/{filename}', [AuthenticatedLivewireFilePreviewController::class, 'handle'])
    ->name('livewire.preview-file');
