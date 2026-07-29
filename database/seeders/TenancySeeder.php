<?php

namespace Database\Seeders;

use App\Enums\DomainVerificationStatus;
use App\Enums\StoreDomainType;
use App\Enums\TenantMembershipRole;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Non-production seed data: one platform admin, two tenants each with an
 * owner and a staff member, a store, and a verified subdomain. Later
 * phases' seeders (catalog, orders, vouchers, ...) build on top of the
 * tenants created here. Credentials are intentionally simple/documented —
 * never used outside local dev.
 */
class TenancySeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@usaharumahan.id'],
            [
                'name' => 'Admin Platform',
                'password' => 'password',
                'email_verified_at' => now(),
                'is_platform_admin' => true,
            ],
        );

        $this->createTenant(
            name: 'Dapur Ibu',
            slug: 'dapur-ibu',
            ownerEmail: 'owner@dapuribu.test',
            staffEmail: 'staff@dapuribu.test',
        );

        $this->createTenant(
            name: 'Kerajinan Lombok',
            slug: 'kerajinan-lombok',
            ownerEmail: 'owner@kerajinanlombok.test',
            staffEmail: 'staff@kerajinanlombok.test',
        );
    }

    private function createTenant(string $name, string $slug, string $ownerEmail, string $staffEmail): void
    {
        $owner = User::query()->updateOrCreate(
            ['email' => $ownerEmail],
            ['name' => "Pemilik {$name}", 'password' => 'password', 'email_verified_at' => now()],
        );

        $staff = User::query()->updateOrCreate(
            ['email' => $staffEmail],
            ['name' => "Staf {$name}", 'password' => 'password', 'email_verified_at' => now()],
        );

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'owner_user_id' => $owner->id, 'trial_starts_at' => now(), 'trial_ends_at' => now()->addDays(14)],
        );

        TenantMembership::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $owner->id],
            ['role' => TenantMembershipRole::Owner],
        );

        TenantMembership::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'user_id' => $staff->id],
            ['role' => TenantMembershipRole::Staff],
        );

        Store::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'name' => $name,
                'whatsapp_number' => '+62812'.random_int(10000000, 99999999),
                'is_open' => true,
                'is_published' => true,
            ],
        );

        StoreDomain::query()->updateOrCreate(
            ['hostname' => $slug.'.'.config('tenancy.root_domain')],
            [
                'tenant_id' => $tenant->id,
                'type' => StoreDomainType::Subdomain,
                'is_primary' => true,
                'verification_status' => DomainVerificationStatus::Verified,
                'verified_at' => now(),
            ],
        );
    }
}
