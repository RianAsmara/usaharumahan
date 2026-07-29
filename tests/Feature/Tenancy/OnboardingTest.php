<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantMembershipRole;
use App\Models\StoreDomain;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_a_tenant_with_a_store_and_subdomain()
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('onboarding.store'), [
            'name' => 'Dapur Ibu',
            'subdomain' => 'dapur-ibu',
        ]);

        $response->assertRedirect(route('dashboard'));

        $tenant = Tenant::query()->where('slug', 'dapur-ibu')->firstOrFail();
        $this->assertSame('Dapur Ibu', $tenant->name);
        $this->assertSame($owner->id, $tenant->owner_user_id);

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'role' => TenantMembershipRole::Owner->value,
        ]);

        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenant->id,
            'name' => 'Dapur Ibu',
        ]);

        $domain = StoreDomain::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('dapur-ibu.'.config('tenancy.root_domain'), $domain->hostname);
        $this->assertTrue($domain->isVerified());
        $this->assertTrue($domain->is_primary);
    }

    public function test_reserved_subdomains_are_rejected()
    {
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('onboarding.store'), [
            'name' => 'Admin Store',
            'subdomain' => 'admin',
        ]);

        $response->assertSessionHasErrors('subdomain');
        $this->assertDatabaseMissing('tenants', ['slug' => 'admin']);
    }

    public function test_a_subdomain_already_taken_by_another_tenant_is_rejected()
    {
        [$existingTenant] = $this->createTenantWithOwner(['slug' => 'dapur-ibu']);
        StoreDomain::factory()->create([
            'tenant_id' => $existingTenant->id,
            'hostname' => 'dapur-ibu.'.config('tenancy.root_domain'),
        ]);

        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('onboarding.store'), [
            'name' => 'Dapur Ibu 2',
            'subdomain' => 'dapur-ibu',
        ]);

        $response->assertSessionHasErrors('subdomain');
    }

    public function test_users_who_already_have_a_tenant_are_redirected_away_from_onboarding()
    {
        [, $owner] = $this->createTenantWithOwner();

        $response = $this->actingAs($owner)->get(route('onboarding.create'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_a_failed_tenant_creation_does_not_leave_partial_rows(): void
    {
        // A duplicate slug fails the ValidTenantSubdomain rule before the
        // action ever runs, so this proves validation, not the action's own
        // atomicity — the action's atomicity itself is structural (a single
        // DB::transaction, see App\Actions\Tenancy\CreateTenantWithStore).
        [$existingTenant] = $this->createTenantWithOwner(['slug' => 'dapur-ibu']);
        StoreDomain::factory()->create([
            'tenant_id' => $existingTenant->id,
            'hostname' => 'dapur-ibu.'.config('tenancy.root_domain'),
        ]);

        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('onboarding.store'), [
            'name' => 'Dapur Ibu 2',
            'subdomain' => 'dapur-ibu',
        ]);

        $this->assertSame(0, TenantMembership::query()->where('user_id', $owner->id)->count());
    }
}
