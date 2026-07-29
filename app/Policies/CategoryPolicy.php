<?php

namespace App\Policies;

use App\Enums\TenantMembershipRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenantMemberships()->exists();
    }

    public function view(User $user, Category $category): bool
    {
        return $this->membershipFor($user, $category) !== null;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantMembershipRole::Owner)
            ->exists();
    }

    public function update(User $user, Category $category): bool
    {
        return $this->membershipFor($user, $category)?->role === TenantMembershipRole::Owner;
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }

    private function membershipFor(User $user, Category $category): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $category->tenant_id)
            ->first();
    }
}
