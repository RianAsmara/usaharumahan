<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Services\Tenancy\HostnameResolver;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Runs globally on every request (see bootstrap/app.php) and resolves the
 * Host header against store_domains. It is deliberately "soft": the same
 * "/" route serves both the marketing/dashboard root domain and every
 * tenant storefront, branching on whether a tenant was resolved — so a
 * host that isn't a registered tenant domain is simply treated as the
 * root/app domain here, not a 404. Routes that genuinely require a
 * resolved tenant (storefront product/cart/checkout pages, Phase 3+) are
 * separately protected by the `RequireTenant` middleware, which does 404.
 * There is deliberately no fallback tenant either way — see docs/TENANCY.md §2.
 */
class ResolveTenantFromHostname
{
    public function __construct(
        private readonly HostnameResolver $resolver,
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolve($request->getHost());

        if ($tenant === null) {
            return $next($request);
        }

        if ($tenant->status !== TenantStatus::Active) {
            throw new HttpException(403, 'This store is currently unavailable.');
        }

        $this->tenantContext->set($tenant);

        return $next($request);
    }
}
