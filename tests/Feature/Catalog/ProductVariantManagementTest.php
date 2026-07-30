<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductVariantManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_add_a_variant_with_option_values(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'L']);

        $response = $this->actingAs($owner)->post(route('product-variants.store', $product), [
            'price' => 25000,
            'option_value_ids' => [$value->id],
            'sku_suffix' => 'L',
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_option_values_from_another_product_are_rejected(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $otherProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
        $foreignOption = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $otherProduct->id]);
        $foreignValue = ProductOptionValue::factory()->create(['product_option_id' => $foreignOption->id]);

        $response = $this->actingAs($owner)->post(route('product-variants.store', $product), [
            'price' => 25000,
            'option_value_ids' => [$foreignValue->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_update_a_variants_price(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'price' => 10000]);

        $response = $this->actingAs($owner)->put(route('product-variants.update', [$product, $variant]), [
            'price' => 12000,
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(12000, $variant->fresh()->price);
    }

    public function test_the_last_remaining_variant_cannot_be_deleted(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $response = $this->actingAs($owner)->delete(route('product-variants.destroy', [$product, $variant]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_staff_cannot_add_update_or_delete_variants(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $this->actingAs($staff)->post(route('product-variants.store', $product), ['price' => 1000])->assertForbidden();
        $this->actingAs($staff)->put(route('product-variants.update', [$product, $variant]), ['price' => 1000])->assertForbidden();
        $this->actingAs($staff)->delete(route('product-variants.destroy', [$product, $variant]))->assertForbidden();
    }
}
