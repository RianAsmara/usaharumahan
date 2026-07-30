<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\TenantMembership;
use App\Models\User;

class InventoryPolicy
{
    public function adjust(User $user, ProductVariant $variant): bool
    {
        return $this->membershipFor($user, $variant) !== null;
    }

    private function membershipFor(User $user, ProductVariant $variant): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $variant->tenant_id)
            ->first();
    }
}
