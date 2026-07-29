<?php

use App\Http\Controllers\PlatformAdmin\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'platform.admin'])
    ->prefix('platform')
    ->name('platform.')
    ->group(function () {
        Route::get('tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    });
