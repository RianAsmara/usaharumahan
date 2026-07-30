<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_a_product_and_is_redirected_to_edit(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('products.store'), [
            'name' => 'Keripik Singkong',
            'category_id' => $category->id,
            'description' => 'Renyah',
            'price' => 15000,
        ]);

        $product = Product::query()->where('tenant_id', $tenant->id)->sole();
        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(15000, $product->variants()->sole()->price);
    }

    public function test_a_category_from_another_tenant_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        [$otherTenant] = $this->createTenantWithOwner();
        $foreignCategory = Category::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($owner)->post(route('products.store'), [
            'name' => 'Produk',
            'category_id' => $foreignCategory->id,
            'price' => 1000,
        ]);

        $response->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('products', ['name' => 'Produk']);
    }

    public function test_staff_cannot_create_a_product(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);

        $response = $this->actingAs($staff)->post(route('products.store'), [
            'name' => 'Produk Staff',
            'price' => 1000,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('products', ['name' => 'Produk Staff']);
    }

    public function test_owner_can_update_a_products_status_and_it_publishes_once(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Draft]);

        $response = $this->actingAs($owner)->put(route('products.update', $product), [
            'name' => $product->name,
            'status' => ProductStatus::Active->value,
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $product->refresh();
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertNotNull($product->published_at);
    }

    public function test_staff_can_view_but_not_update_a_product(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)->get(route('products.edit', $product))->assertOk();

        $response = $this->actingAs($staff)->put(route('products.update', $product), [
            'name' => 'Diubah Staff',
            'status' => ProductStatus::Draft->value,
        ]);

        $response->assertForbidden();
    }

    public function test_owner_can_delete_a_product(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->delete(route('products.destroy', $product));

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_an_owner_cannot_view_or_edit_another_tenants_product(): void
    {
        [, $ownerA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();
        $productB = Product::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($ownerA)->get(route('products.edit', $productB))->assertForbidden();
        $this->actingAs($ownerA)->put(route('products.update', $productB), ['name' => 'Dibajak', 'status' => ProductStatus::Draft->value])->assertForbidden();
    }
}
