<?php

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Models\StoreDomain;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function domainFor(Tenant $tenant): string
    {
        $hostname = 'toko-'.$tenant->id.'.usaharumahan.localhost';
        StoreDomain::factory()->create(['tenant_id' => $tenant->id, 'hostname' => $hostname]);

        return $hostname;
    }

    public function test_it_renders_variants_options_and_stock_for_a_published_product(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $product = Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'keripik-singkong',
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->subDay(),
        ]);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'name' => 'Ukuran']);
        $value = ProductOptionValue::factory()->create(['tenant_id' => $tenant->id, 'product_option_id' => $option->id, 'value' => '250g']);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'price' => 15000]);
        $variant->optionValues()->attach($value->id);
        $variant->inventory()->create(['tenant_id' => $tenant->id, 'on_hand' => 10, 'reserved' => 2]);

        $response = $this->get("http://{$hostname}/produk/keripik-singkong");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('storefront/products/show')
            ->where('product.name', $product->name)
            ->has('product.options', 1)
            ->where('product.options.0.values.0.id', $value->id)
            ->has('product.variants', 1)
            ->where('product.variants.0.optionValueIds.0', $value->id)
            ->where('product.variants.0.stock', 8)
        );
    }

    public function test_it_404s_for_a_draft_product(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);
        Product::factory()->create(['tenant_id' => $tenant->id, 'slug' => 'draf', 'status' => ProductStatus::Draft]);

        $response = $this->get("http://{$hostname}/produk/draf");

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page->component('storefront/not-found'));
    }

    public function test_it_404s_for_an_archived_product(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'arsip',
            'status' => ProductStatus::Archived,
            'published_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->get("http://{$hostname}/produk/arsip");

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page->component('storefront/not-found'));
    }

    public function test_it_404s_for_a_not_yet_published_product(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);
        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'akan-datang',
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->addDay(),
        ]);

        $response = $this->get("http://{$hostname}/produk/akan-datang");

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page->component('storefront/not-found'));
    }

    public function test_it_404s_for_a_product_belonging_to_a_different_tenant(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        [$otherTenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        Product::factory()->create([
            'tenant_id' => $otherTenant->id,
            'slug' => 'punya-toko-lain',
            'status' => ProductStatus::Active,
            'published_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->get("http://{$hostname}/produk/punya-toko-lain");

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page->component('storefront/not-found'));
    }

    public function test_it_404s_for_a_nonexistent_slug(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $response = $this->get("http://{$hostname}/produk/tidak-ada");

        $response->assertNotFound();
        $response->assertInertia(fn ($page) => $page->component('storefront/not-found'));
    }
}
