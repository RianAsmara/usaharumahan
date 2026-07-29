<?php

namespace App\Policies;

use App\Enums\TenantMembershipRole;
use App\Models\Store;
use App\Models\TenantMembership;
use App\Models\User;

class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        return $this->membershipFor($user, $store) !== null;
    }

    public function update(User $user, Store $store): bool
    {
        return $this->membershipFor($user, $store)?->role === TenantMembershipRole::Owner;
    }

    private function membershipFor(User $user, Store $store): ?TenantMembership
    {
        // Re-checked independently of however $store was fetched — a policy
        // must not assume the query that produced $store already scoped it
        // to this user's tenant. See docs/TENANCY.md §5.
        return $user->tenantMemberships()
            ->where('tenant_id', $store->tenant_id)
            ->first();
    }
}
