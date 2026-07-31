<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_published_scope_includes_only_active_products_with_a_past_published_at(): void
    {
        [$tenant] = $this->createTenantWithOwner();

        $published = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->subDay(),
        ]);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ProductStatus::Draft,
            'published_at' => Carbon::now()->subDay(),
        ]);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ProductStatus::Archived,
            'published_at' => Carbon::now()->subDay(),
        ]);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ProductStatus::Active,
            'published_at' => null,
        ]);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->addDay(),
        ]);

        $result = Product::query()->published()->get();

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->is($published));
    }

    public function test_display_price_is_the_cheapest_effective_price_across_variants(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'price' => 30000, 'sale_price' => null]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'price' => 50000, 'sale_price' => 20000]);

        $product->load('variants');

        $this->assertSame(['amount' => 20000, 'isFrom' => true], $product->displayPrice());
    }

    public function test_display_price_is_not_marked_from_when_all_variants_share_one_effective_price(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'price' => 25000, 'sale_price' => null]);

        $product->load('variants');

        $this->assertSame(['amount' => 25000, 'isFrom' => false], $product->displayPrice());
    }

    public function test_is_in_stock_is_true_when_any_variant_has_available_inventory(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $outOfStock = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $inStock = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $outOfStock->inventory()->create(['tenant_id' => $tenant->id, 'on_hand' => 0, 'reserved' => 0]);
        $inStock->inventory()->create(['tenant_id' => $tenant->id, 'on_hand' => 5, 'reserved' => 2]);

        $product->load('variants.inventory');

        $this->assertTrue($product->isInStock());
    }

    public function test_is_in_stock_is_false_when_no_variant_has_available_inventory(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $variant->inventory()->create(['tenant_id' => $tenant->id, 'on_hand' => 3, 'reserved' => 3]);

        $product->load('variants.inventory');

        $this->assertFalse($product->isInStock());
    }
}
