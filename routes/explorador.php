<?php

use App\Livewire\Explorador\ExploradorGlobal;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'erp.employee', 'no-cache'])
    ->get('/explorer', ExploradorGlobal::class)
    ->name('explorer.index');
