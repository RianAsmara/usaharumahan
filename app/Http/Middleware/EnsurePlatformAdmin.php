<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Platform-admin access is a deliberate, separate gate — never a side
 * effect of an owner/staff policy returning true. See docs/TENANCY.md §7.
 */
class EnsurePlatformAdmin
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_platform_admin === true, 403);

        $this->tenantContext->markPlatformAdminContext();

        return $next($request);
    }
}
