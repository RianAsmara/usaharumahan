<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * For routes that only make sense on a resolved tenant storefront (product
 * listing, cart, checkout — Phase 3+). ResolveTenantFromHostname runs first
 * and is soft (falls through for the root domain); this one is not.
 */
class RequireTenant
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenantContext->hasTenant()) {
            throw new HttpException(404, 'Store not found.');
        }

        return $next($request);
    }
}
