<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_a_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();

        $response = $this->actingAs($owner)->post(route('categories.store'), [
            'name' => 'Makanan Ringan',
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'tenant_id' => $tenant->id,
            'name' => 'Makanan Ringan',
            'slug' => 'makanan-ringan',
        ]);
    }

    public function test_owner_can_update_a_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->put(route('categories.update', $category), [
            'name' => 'Kue Kering',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Kue Kering',
            'is_active' => false,
        ]);
    }

    public function test_owner_can_delete_a_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_staff_cannot_create_update_or_delete_categories(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)->post(route('categories.store'), ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($staff)->put(route('categories.update', $category), ['name' => 'Diubah'])->assertForbidden();
        $this->actingAs($staff)->delete(route('categories.destroy', $category))->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Baru']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => $category->name]);
    }

    public function test_staff_can_still_view_the_category_list(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($staff)->get(route('categories.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->where('can.create', false));
    }

    public function test_a_tenant_never_sees_another_tenants_categories(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();
        Category::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Milik Tenant B']);

        $response = $this->actingAs($ownerA)->get(route('categories.index'));

        $response->assertInertia(fn ($page) => $page->has('categories', 0));
    }

    public function test_an_owner_cannot_update_another_tenants_category(): void
    {
        [, $ownerA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();
        $categoryB = Category::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($ownerA)->put(route('categories.update', $categoryB), ['name' => 'Dibajak'])
            ->assertForbidden();
    }
}
