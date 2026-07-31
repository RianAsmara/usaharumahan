<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreDomain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function domainFor(Tenant $tenant): string
    {
        $hostname = 'toko-'.$tenant->id.'.usaharumahan.localhost';
        StoreDomain::factory()->create(['tenant_id' => $tenant->id, 'hostname' => $hostname]);

        return $hostname;
    }

    public function test_home_lists_only_published_products(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $published = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->subDay(),
        ]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $published->id, 'price' => 15000]);

        Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Draft, 'published_at' => Carbon::now()->subDay()]);
        Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Archived, 'published_at' => Carbon::now()->subDay()]);
        Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Active, 'published_at' => Carbon::now()->addDay()]);

        $response = $this->get("http://{$hostname}/");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('storefront/home')
            ->has('products', 1)
            ->where('products.0.id', $published->id)
        );
    }

    public function test_home_excludes_other_tenants_products(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        [$otherTenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $foreign = Product::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->subDay(),
        ]);
        ProductVariant::factory()->create(['tenant_id' => $otherTenant->id, 'product_id' => $foreign->id]);

        $response = $this->get("http://{$hostname}/");

        $response->assertInertia(fn ($page) => $page->component('storefront/home')->has('products', 0));
    }

    public function test_home_shows_mulai_dari_price_when_variants_differ_and_plain_price_when_they_do_not(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $varied = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Active, 'published_at' => Carbon::now()->subDay()]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $varied->id, 'price' => 10000]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $varied->id, 'price' => 20000]);

        $flat = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Active, 'published_at' => Carbon::now()->subDay()]);
        ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $flat->id, 'price' => 30000]);

        $response = $this->get("http://{$hostname}/");
        $products = collect($response->viewData('page')['props']['products']);

        $this->assertTrue($products->firstWhere('id', $varied->id)['price']['isFrom']);
        $this->assertFalse($products->firstWhere('id', $flat->id)['price']['isFrom']);
    }

    public function test_home_shows_correct_stock_state(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'status' => ProductStatus::Active, 'published_at' => Carbon::now()->subDay()]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $variant->inventory()->create(['tenant_id' => $tenant->id, 'on_hand' => 0, 'reserved' => 0]);

        $response = $this->get("http://{$hostname}/");

        $response->assertInertia(fn ($page) => $page->where('products.0.inStock', false));
    }
}
