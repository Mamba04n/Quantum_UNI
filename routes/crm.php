<?php

use App\Livewire\Crm\ClientManager;
use App\Livewire\Crm\ClientProfile;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache', 'permission:crm.clients.manage'])->group(function (): void {
    Route::get('/clients', ClientManager::class)->name('clients.index');
    Route::get('/clients/{id}/profile', ClientProfile::class)->name('clients.profile');
});
