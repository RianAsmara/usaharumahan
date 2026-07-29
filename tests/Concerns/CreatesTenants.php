<?php

namespace Tests\Concerns;

use App\Enums\TenantMembershipRole;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

trait CreatesTenants
{
    /**
     * @return array{0: Tenant, 1: User}
     */
    protected function createTenantWithOwner(array $tenantAttributes = []): array
    {
        $owner = User::factory()->create();

        $tenant = Tenant::factory()->create([
            'owner_user_id' => $owner->id,
            ...$tenantAttributes,
        ]);

        TenantMembership::factory()->owner()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
        ]);

        Store::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $owner];
    }

    protected function addStaffToTenant(Tenant $tenant): User
    {
        $staff = User::factory()->create();

        TenantMembership::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'role' => TenantMembershipRole::Staff,
        ]);

        return $staff;
    }
}
