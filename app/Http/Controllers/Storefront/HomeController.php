<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
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

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->with([
                'images' => fn ($query) => $query->limit(1),
                'variants.inventory',
            ])
            ->latest()
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'imageUrl' => $product->images->first()?->url,
                'price' => $product->displayPrice(),
                'inStock' => $product->isInStock(),
            ]);

        return Inertia::render('storefront/home', [
            'store' => $store->toStorefrontArray(),
            'products' => $products,
        ]);
    }
}
