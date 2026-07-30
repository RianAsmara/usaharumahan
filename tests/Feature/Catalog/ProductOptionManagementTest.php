<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductOptionManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_add_an_option_with_values(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('product-options.store', $product), [
            'name' => 'Ukuran',
            'values' => ['S', 'M', 'L'],
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $option = $product->options()->sole();
        $this->assertSame('Ukuran', $option->name);
        $this->assertSame(3, $option->values()->count());
    }

    public function test_a_fourth_option_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        ProductOption::factory()->count(3)->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $response = $this->actingAs($owner)->post(route('product-options.store', $product), [
            'name' => 'Warna',
            'values' => ['Merah'],
        ]);

        $response->assertStatus(422);
    }

    public function test_more_than_20_values_is_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('product-options.store', $product), [
            'name' => 'Ukuran',
            'values' => array_map(fn ($i) => "Nilai {$i}", range(1, 21)),
        ]);

        $response->assertSessionHasErrors('values');
    }

    public function test_owner_can_delete_an_option(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $response = $this->actingAs($owner)->delete(route('product-options.destroy', [$product, $option]));

        $response->assertRedirect(route('products.edit', $product));
        $this->assertDatabaseMissing('product_options', ['id' => $option->id]);
    }

    public function test_staff_cannot_add_or_delete_options(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $this->actingAs($staff)->post(route('product-options.store', $product), [
            'name' => 'Warna',
            'values' => ['Merah'],
        ])->assertForbidden();

        $this->actingAs($staff)->delete(route('product-options.destroy', [$product, $option]))->assertForbidden();
    }
}
