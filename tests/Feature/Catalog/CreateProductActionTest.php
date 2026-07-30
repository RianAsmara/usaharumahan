<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\CreateProduct;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CreateProductActionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_it_creates_a_product_with_a_default_variant_and_zero_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $product = app(CreateProduct::class)->handle(
            tenant: $tenant,
            name: 'Keripik Singkong',
            categoryId: $category->id,
            description: 'Renyah dan gurih',
            price: 15000,
        );

        $this->assertSame('keripik-singkong', $product->slug);
        $this->assertSame($category->id, $product->category_id);

        $variant = $product->variants()->sole();
        $this->assertSame(15000, $variant->price);
        $this->assertNotEmpty($variant->sku);
        $this->assertSame(0, $variant->inventory->on_hand);
    }

    public function test_two_products_with_the_same_name_get_distinct_slugs(): void
    {
        [$tenant] = $this->createTenantWithOwner();

        $first = app(CreateProduct::class)->handle($tenant, 'Keripik Singkong', null, null, 15000);
        $second = app(CreateProduct::class)->handle($tenant, 'Keripik Singkong', null, null, 16000);

        $this->assertSame('keripik-singkong', $first->slug);
        $this->assertSame('keripik-singkong-2', $second->slug);
    }
}
