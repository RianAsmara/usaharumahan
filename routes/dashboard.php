<?php

use App\Http\Controllers\Dashboard\CategoryController;
use App\Http\Controllers\Dashboard\ProductController;
use App\Http\Controllers\Dashboard\ProductImageController;
use App\Http\Controllers\Dashboard\ProductOptionController;
use App\Http\Controllers\Dashboard\ProductVariantController;
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

    Route::get('dashboard/products', [ProductController::class, 'index'])->name('products.index');
    Route::get('dashboard/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('dashboard/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('dashboard/products/{product}', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('dashboard/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('dashboard/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::post('dashboard/products/{product}/options', [ProductOptionController::class, 'store'])->name('product-options.store');
    Route::delete('dashboard/products/{product}/options/{option}', [ProductOptionController::class, 'destroy'])->name('product-options.destroy');

    Route::post('dashboard/products/{product}/variants', [ProductVariantController::class, 'store'])->name('product-variants.store');
    Route::put('dashboard/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('product-variants.update');
    Route::delete('dashboard/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('product-variants.destroy');

    Route::post('dashboard/products/{product}/images', [ProductImageController::class, 'store'])->name('product-images.store');
    Route::put('dashboard/products/{product}/images/reorder', [ProductImageController::class, 'reorder'])->name('product-images.reorder');
    Route::delete('dashboard/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('product-images.destroy');
});
