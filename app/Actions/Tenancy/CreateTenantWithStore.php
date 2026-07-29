<?php

namespace App\Actions\Tenancy;

use App\Enums\DomainVerificationStatus;
use App\Enums\StoreDomainType;
use App\Enums\TenantMembershipRole;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The whole owner-onboarding "create my business" step: a tenant, its owner
 * membership, its store profile, and its subdomain, created together so a
 * failure partway through can never leave a tenant without a store or an
 * owner without a membership.
 */
class CreateTenantWithStore
{
    public function handle(User $owner, string $name, string $subdomain): Tenant
    {
        return DB::transaction(function () use ($owner, $name, $subdomain) {
            $tenant = Tenant::query()->create([
                'name' => $name,
                'slug' => $subdomain,
                'owner_user_id' => $owner->id,
                'trial_starts_at' => now(),
                'trial_ends_at' => now()->addDays(14),
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $owner->id,
                'role' => TenantMembershipRole::Owner,
            ]);

            Store::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $name,
            ]);

            // Subdomains are under our own root domain, so there is no DNS
            // proof to collect — they're verified the moment they're
            // created. Custom domains (Phase 6) start Pending instead.
            StoreDomain::query()->create([
                'tenant_id' => $tenant->id,
                'hostname' => $subdomain.'.'.config('tenancy.root_domain'),
                'type' => StoreDomainType::Subdomain,
                'is_primary' => true,
                'verification_status' => DomainVerificationStatus::Verified,
                'verified_at' => now(),
            ]);

            return $tenant;
        });
    }
}
