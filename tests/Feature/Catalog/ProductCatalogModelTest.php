<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductCatalogModelTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_product_has_images_options_and_variants(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $product->images()->create(['tenant_id' => $tenant->id, 'path' => 'a.jpg', 'sort_order' => 0]);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'name' => 'Ukuran']);
        $value = ProductOptionValue::factory()->create(['tenant_id' => $tenant->id, 'product_option_id' => $option->id, 'value' => 'M']);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $variant->optionValues()->attach($value->id);

        $product->refresh();

        $this->assertCount(1, $product->images);
        $this->assertCount(1, $product->options);
        $this->assertCount(1, $product->variants);
        $this->assertTrue($variant->optionValues->first()->is($value));
        $this->assertTrue($value->variants->first()->is($variant));
    }

    public function test_a_variant_sku_is_unique_per_tenant_not_globally(): void
    {
        [$tenantA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();

        ProductVariant::factory()->create(['tenant_id' => $tenantA->id, 'sku' => 'SKU-SAMA']);
        $variantB = ProductVariant::factory()->create(['tenant_id' => $tenantB->id, 'sku' => 'SKU-SAMA']);

        $this->assertSame('SKU-SAMA', $variantB->sku);
    }

    public function test_a_variant_sku_is_rejected_when_duplicated_within_the_same_tenant(): void
    {
        [$tenant] = $this->createTenantWithOwner();

        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'SKU-SAMA']);

        $this->expectException(QueryException::class);

        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'sku' => 'SKU-SAMA']);
    }
}
