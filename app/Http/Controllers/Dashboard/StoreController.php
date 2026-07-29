<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function edit(TenantContext $tenantContext): Response
    {
        $store = $tenantContext->tenant()->store;

        Gate::authorize('view', $store);

        return Inertia::render('dashboard/store/edit', [
            'store' => $store,
            'can' => [
                'update' => Gate::allows('update', $store),
            ],
        ]);
    }

    public function update(UpdateStoreRequest $request, TenantContext $tenantContext): RedirectResponse
    {
        $store = $tenantContext->tenant()->store;

        Gate::authorize('update', $store);

        $store->update($request->validated());

        return redirect()->route('store.edit');
    }
}
