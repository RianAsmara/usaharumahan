<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use RuntimeException;

/**
 * The single source of truth for "which tenant is this request for," bound
 * as a singleton and populated once per request by tenant-resolution
 * middleware (hostname-based for the storefront, membership-based for the
 * dashboard — see docs/TENANCY.md). Nothing else in the app is allowed to
 * decide the current tenant; everything reads it from here.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    private bool $platformAdminContext = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function markPlatformAdminContext(): void
    {
        $this->platformAdminContext = true;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new RuntimeException('No tenant has been resolved for this request.');
        }

        return $this->tenant;
    }

    public function tenantId(): string
    {
        return $this->tenant()->id;
    }

    public function isPlatformAdminContext(): bool
    {
        return $this->platformAdminContext;
    }
}
