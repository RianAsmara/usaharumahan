<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Support\Catalog\UniqueSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CategoryModelTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_category_belongs_to_a_tenant(): void
    {
        [$tenant] = $this->createTenantWithOwner();

        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($category->tenant->is($tenant));
    }

    public function test_slugs_are_unique_per_tenant_not_globally(): void
    {
        [$tenantA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();

        Category::factory()->create(['tenant_id' => $tenantA->id, 'slug' => 'makanan']);
        $sameSlugDifferentTenant = Category::factory()->create(['tenant_id' => $tenantB->id, 'slug' => 'makanan']);

        $this->assertSame('makanan', $sameSlugDifferentTenant->slug);
    }

    public function test_unique_slug_helper_appends_a_suffix_on_collision(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        Category::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'makanan']);

        $slug = UniqueSlug::generate('categories', $tenant->id, 'Makanan');

        $this->assertSame('makanan-2', $slug);
    }

    public function test_unique_slug_helper_ignores_a_given_id_when_checking_collisions(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'makanan']);

        $slug = UniqueSlug::generate('categories', $tenant->id, 'Makanan', ignoreId: $category->id);

        $this->assertSame('makanan', $slug);
    }
}
