<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_without_a_tenant_are_redirected_to_onboarding()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('onboarding.create'));
    }

    public function test_authenticated_users_with_a_tenant_can_visit_the_dashboard()
    {
        [, $owner] = $this->createTenantWithOwner();
        $this->actingAs($owner);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }
}
