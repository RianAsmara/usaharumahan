<?php

use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use Illuminate\Support\Facades\Route;

// Serves either the marketing welcome page or a tenant's storefront home,
// depending on whether ResolveTenantFromHostname (global web middleware)
// resolved a tenant for this request's Host header. See HomeController.
Route::get('/', HomeController::class)->name('home');

Route::get('/produk/{slug}', [ProductController::class, 'show'])
    ->middleware('tenant.required')
    ->name('storefront.product');

require __DIR__.'/settings.php';
require __DIR__.'/onboarding.php';
require __DIR__.'/dashboard.php';
require __DIR__.'/platform.php';
require __DIR__.'/auth.php';
