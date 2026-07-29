<?php

namespace Tests\Feature\Tenancy;

use App\Models\StoreDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * Proves the negative directly, per docs/TENANCY.md §8: a member of tenant A
 * must never see or affect tenant B's data, even when nothing in the
 * request explicitly names tenant B.
 */
class CrossTenantIsolationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_an_owners_dashboard_only_ever_shows_their_own_tenants_store()
    {
        [$tenantA, $ownerA] = $this->createTenantWithOwner(['name' => 'Toko A']);
        [$tenantB] = $this->createTenantWithOwner(['name' => 'Toko B']);

        $response = $this->actingAs($ownerA)->get(route('store.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('store.name', $tenantA->store->name)
            ->where('store.name', fn (string $name) => $name !== $tenantB->store->name),
        );
    }

    public function test_updating_the_store_never_touches_another_tenants_store_even_with_a_shared_session()
    {
        [$tenantA, $ownerA] = $this->createTenantWithOwner();
        [$tenantB, $ownerB] = $this->createTenantWithOwner();

        $this->actingAs($ownerA)->put(route('store.update'), ['name' => 'Diubah oleh A']);

        $this->assertDatabaseHas('stores', ['tenant_id' => $tenantA->id, 'name' => 'Diubah oleh A']);
        $this->assertDatabaseMissing('stores', ['tenant_id' => $tenantB->id, 'name' => 'Diubah oleh A']);

        // Sanity check the fixture itself: B's owner really is a different
        // user than A's owner, so the isolation above isn't accidental.
        $this->assertNotSame($ownerA->id, $ownerB->id);
    }

    public function test_a_staff_member_of_one_tenant_has_no_membership_in_another()
    {
        [$tenantA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();
        $staffOfA = $this->addStaffToTenant($tenantA);

        $this->assertTrue($staffOfA->tenantMemberships()->where('tenant_id', $tenantA->id)->exists());
        $this->assertFalse($staffOfA->tenantMemberships()->where('tenant_id', $tenantB->id)->exists());
    }

    public function test_tenant_a_customers_never_resolve_tenant_bs_storefront()
    {
        [$tenantA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();

        StoreDomain::factory()->create([
            'tenant_id' => $tenantA->id,
            'hostname' => 'toko-a.usaharumahan.localhost',
        ]);
        StoreDomain::factory()->create([
            'tenant_id' => $tenantB->id,
            'hostname' => 'toko-b.usaharumahan.localhost',
        ]);

        $response = $this->get('http://toko-a.usaharumahan.localhost/');

        $response->assertInertia(fn ($page) => $page->where('store.name', $tenantA->store->name));
        $response->assertInertia(fn ($page) => $page->where('store.name', fn (string $name) => $name !== $tenantB->store->name));
    }
}
