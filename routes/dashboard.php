<?php

use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\StoreController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'tenant.dashboard'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::get('dashboard/store', [StoreController::class, 'edit'])->name('store.edit');
    Route::put('dashboard/store', [StoreController::class, 'update'])->name('store.update');

    Route::get('dashboard/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('dashboard/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('dashboard/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('dashboard/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});
