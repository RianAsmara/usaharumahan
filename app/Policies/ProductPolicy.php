<?php

namespace App\Policies;

use App\Enums\TenantMembershipRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenantMemberships()->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->membershipFor($user, $product) !== null;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantMembershipRole::Owner)
            ->exists();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->membershipFor($user, $product)?->role === TenantMembershipRole::Owner;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    private function membershipFor(User $user, Product $product): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $product->tenant_id)
            ->first();
    }
}
