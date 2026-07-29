<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single "/" route serves two entirely different pages from the same
 * path, branching on whether ResolveTenantFromHostname resolved a tenant
 * for this request's Host header — see docs/TENANCY.md and the middleware's
 * docblock for why this isn't two separate route registrations.
 */
class HomeController extends Controller
{
    public function __invoke(TenantContext $tenantContext): Response
    {
        if (! $tenantContext->hasTenant()) {
            return Inertia::render('welcome');
        }

        $tenant = $tenantContext->tenant();
        $store = $tenant->store;

        return Inertia::render('storefront/home', [
            'store' => [
                'name' => $store->name,
                'description' => $store->description,
                'whatsappNumber' => $store->whatsapp_number,
                'isOpen' => $store->is_open,
                'primaryColor' => $store->primary_color,
            ],
        ]);
    }
}
