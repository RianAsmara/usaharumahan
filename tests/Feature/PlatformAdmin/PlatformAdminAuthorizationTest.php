<?php

namespace Tests\Feature\PlatformAdmin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class PlatformAdminAuthorizationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_guests_cannot_access_platform_admin()
    {
        $response = $this->get(route('platform.tenants.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_a_regular_owner_cannot_access_platform_admin()
    {
        [, $owner] = $this->createTenantWithOwner();

        $response = $this->actingAs($owner)->get(route('platform.tenants.index'));

        $response->assertForbidden();
    }

    public function test_a_platform_admin_can_list_tenants()
    {
        [$tenant] = $this->createTenantWithOwner();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get(route('platform.tenants.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('platform-admin/tenants/index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', $tenant->name),
        );
    }

    public function test_a_platform_admin_can_view_a_single_tenant()
    {
        [$tenant] = $this->createTenantWithOwner();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $response = $this->actingAs($admin)->get(route('platform.tenants.show', $tenant));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('platform-admin/tenants/show')
            ->where('tenant.id', $tenant->id),
        );
    }
}
