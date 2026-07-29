<?php

namespace Tests\Feature\Store;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StoreSettingsTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_view_and_update_store_settings()
    {
        [$tenant, $owner] = $this->createTenantWithOwner();

        $this->actingAs($owner)->get(route('store.edit'))->assertOk();

        $response = $this->actingAs($owner)->put(route('store.update'), [
            'name' => 'Dapur Ibu Baru',
            'whatsapp_number' => '+6281234567890',
            'is_open' => false,
        ]);

        $response->assertRedirect(route('store.edit'));

        $this->assertDatabaseHas('stores', [
            'tenant_id' => $tenant->id,
            'name' => 'Dapur Ibu Baru',
            'whatsapp_number' => '+6281234567890',
            'is_open' => false,
        ]);
    }

    public function test_staff_cannot_update_store_settings()
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);

        $response = $this->actingAs($staff)->put(route('store.update'), [
            'name' => 'Diubah Staff',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('stores', ['name' => 'Diubah Staff']);
    }

    public function test_staff_can_still_view_store_settings()
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);

        $response = $this->actingAs($staff)->get(route('store.edit'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('can.update', false));
    }
}
