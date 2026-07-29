<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Models\TenantMembership;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Dashboard tenant resolution: unlike the storefront, this never trusts the
 * hostname — it resolves from the authenticated user's verified membership,
 * optionally narrowed by a session-selected tenant for owners who belong to
 * more than one. See docs/TENANCY.md §3.
 */
class ResolveTenantForDashboard
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        abort_unless($user !== null, 401);

        $membership = $this->resolveMembership($request, $user);

        if ($membership === null) {
            return redirect()->route('onboarding.create');
        }

        $tenant = $membership->tenant;

        if ($tenant->status !== TenantStatus::Active) {
            throw new HttpException(403, 'This tenant is currently suspended.');
        }

        $request->session()->put('current_tenant_id', $tenant->id);
        $this->tenantContext->set($tenant);

        return $next($request);
    }

    private function resolveMembership(Request $request, User $user): ?TenantMembership
    {
        $memberships = $user->tenantMemberships()->with('tenant')->get();

        if ($memberships->isEmpty()) {
            return null;
        }

        $selectedTenantId = $request->session()->get('current_tenant_id');

        if ($selectedTenantId !== null) {
            $selected = $memberships->firstWhere('tenant_id', $selectedTenantId);

            if ($selected !== null) {
                return $selected;
            }
        }

        return $memberships->first();
    }
}
