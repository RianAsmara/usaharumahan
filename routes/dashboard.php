<?php

use App\Http\Controllers\Dashboard\StoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant.dashboard'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('dashboard/store', [StoreController::class, 'edit'])->name('store.edit');
    Route::put('dashboard/store', [StoreController::class, 'update'])->name('store.update');
});
