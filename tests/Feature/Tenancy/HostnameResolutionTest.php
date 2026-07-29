<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DomainVerificationStatus;
use App\Enums\TenantStatus;
use App\Models\StoreDomain;
use App\Services\Tenancy\HostnameResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class HostnameResolutionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_verified_subdomain_resolves_the_storefront_home_page()
    {
        [$tenant] = $this->createTenantWithOwner();
        StoreDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'dapur-ibu.usaharumahan.localhost',
        ]);

        $response = $this->get('http://dapur-ibu.usaharumahan.localhost/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('storefront/home')
            ->where('store.name', $tenant->store->name),
        );
    }

    public function test_an_unrecognized_hostname_falls_back_to_the_marketing_page_not_a_404()
    {
        $response = $this->get('http://usaharumahan.localhost/');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('welcome'));
    }

    public function test_a_suspended_tenants_subdomain_is_unavailable()
    {
        [$tenant] = $this->createTenantWithOwner();
        $tenant->update(['status' => TenantStatus::Suspended]);
        StoreDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'dapur-ibu.usaharumahan.localhost',
        ]);

        $response = $this->get('http://dapur-ibu.usaharumahan.localhost/');

        $response->assertForbidden();
    }

    public function test_an_unverified_custom_domain_does_not_resolve()
    {
        [$tenant] = $this->createTenantWithOwner();
        StoreDomain::factory()->custom()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'www.dapuribu.com',
            'verification_status' => DomainVerificationStatus::Pending,
        ]);

        $response = $this->get('http://www.dapuribu.com/');

        // Not a registered tenant domain yet, so it falls through to the
        // marketing page rather than resolving to the tenant — see
        // ResolveTenantFromHostname's docblock for why this is "soft."
        $response->assertInertia(fn ($page) => $page->component('welcome'));
    }

    public function test_hostname_normalization_strips_port_and_case()
    {
        [$tenant] = $this->createTenantWithOwner();
        StoreDomain::factory()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'dapur-ibu.usaharumahan.localhost',
        ]);

        $resolved = (new HostnameResolver)->resolve('DAPUR-IBU.usaharumahan.localhost:8080');

        $this->assertNotNull($resolved);
        $this->assertSame($tenant->id, $resolved->id);
    }

    public function test_a_domain_never_resolves_to_a_different_tenant_than_the_one_it_belongs_to()
    {
        [$tenantA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();

        StoreDomain::factory()->create([
            'tenant_id' => $tenantA->id,
            'hostname' => 'dapur-ibu.usaharumahan.localhost',
        ]);

        $resolved = (new HostnameResolver)->resolve('dapur-ibu.usaharumahan.localhost');

        $this->assertSame($tenantA->id, $resolved->id);
        $this->assertNotSame($tenantB->id, $resolved->id);
    }
}
