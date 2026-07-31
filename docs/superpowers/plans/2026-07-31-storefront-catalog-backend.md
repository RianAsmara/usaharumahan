# Storefront Catalog Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire the storefront's public catalog data layer — published-product visibility, the home grid query, and the product detail route — per `docs/superpowers/specs/2026-07-31-storefront-catalog-listing-design.md`. Frontend/visual work (fonts, color system, page rewrites) is a separate follow-up plan against `docs/superpowers/specs/2026-07-31-storefront-visual-design.md`, once this backend slice is in and tested.

**Architecture:** Thin Inertia storefront controllers reading from `TenantContext` (hostname-resolved, not session-selected); a `Product::scopePublished()` local scope as the single source of truth for public visibility, used by both the home grid and detail queries; the product detail route looks up the product manually by `tenant_id` + `slug` rather than route-model binding, since binding can't scope by the hostname-resolved tenant.

**Tech Stack:** Laravel 13 (PHP 8.3), PostgreSQL 16, PHPUnit-style feature tests, Inertia 3 (server-side props only — pages still render the existing placeholder markup, updated for the new props in the follow-up frontend plan).

## Global Constraints

- Run every backend command inside the app container: `docker compose exec app <command>`.
- PostgreSQL only, real DB in tests (no SQLite).
- `Storage::fake('s3')` for every test touching `logo_url`/`banner_url` — the app's default disk is `s3` (MinIO locally).
- `Product::scopePublished()` (data spec's exact definition) is the only place "is this product visible to a customer" is decided — both the home grid query and the detail query must use it, nothing re-implements the condition inline.
- The product detail route looks up the product manually by `tenant_id` + `slug` (never Laravel route-model binding).
- No cart, checkout, WhatsApp order deep-linking, search/category filters, pagination, or SEO metadata — all out of scope (Phase 3+), per the data spec.
- `resources/js/actions/**` (Wayfinder) regenerate automatically — run `docker compose exec app php artisan wayfinder:generate` after adding the new route in Task 4 rather than hand-writing the action file.
- This plan does not touch any `.tsx` page content beyond what's needed to keep existing tests passing — visual design is out of scope here (see the follow-up plan).

---

## File Structure

```
app/Models/Product.php                              (modify — Task 1)
tests/Feature/Catalog/ProductCatalogModelTest.php    (modify — Task 1)
app/Models/Store.php                                 (modify — Task 2)
tests/Feature/Store/StoreModelTest.php               (create — Task 2)
app/Http/Controllers/Storefront/HomeController.php   (modify — Task 3)
tests/Feature/Storefront/ProductListingTest.php       (create — Task 3)
app/Http/Controllers/Storefront/ProductController.php (create — Task 4)
routes/web.php                                        (modify — Task 4)
tests/Feature/Storefront/ProductDetailTest.php         (create — Task 4)
resources/js/pages/storefront/products/show.tsx       (create, minimal — Task 4)
```

---

### Task 1: `Product` model — `scopePublished`, `displayPrice()`, `isInStock()`

**Files:**
- Modify: `app/Models/Product.php`
- Test: `tests/Feature/Catalog/ProductCatalogModelTest.php` (append methods)

**Interfaces:**
- Produces: `Product::scopePublished(Builder $query): void` — local scope, usable as `Product::published()`.
- Produces: `Product->displayPrice(): array{amount: int, isFrom: bool}` — requires `variants` to already be loaded (uses the in-memory collection, does not query).
- Produces: `Product->isInStock(): bool` — requires `variants.inventory` to already be loaded.
- Consumed by: Task 3 (`HomeController`) and Task 4 (`ProductController`).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Catalog/ProductCatalogModelTest.php` (add these imports at the top alongside the existing ones: `use App\Enums\ProductStatus;` and `use Illuminate\Support\Carbon;`):

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app php artisan test --filter=ProductCatalogModelTest`
Expected: FAIL — `Call to undefined method App\Models\Product::published()` (and similar for `displayPrice`/`isInStock`).

- [ ] **Step 3: Implement**

In `app/Models/Product.php`, add the import `use Illuminate\Database\Eloquent\Builder;` and these three methods to the class:

```php
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Active)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @return array{amount: int, isFrom: bool}
     */
    public function displayPrice(): array
    {
        $effectivePrices = $this->variants->map(
            fn (ProductVariant $variant): int => $variant->sale_price ?? $variant->price,
        );

        return [
            'amount' => $effectivePrices->min(),
            'isFrom' => $effectivePrices->unique()->count() > 1,
        ];
    }

    public function isInStock(): bool
    {
        return $this->variants->contains(
            fn (ProductVariant $variant): bool => $variant->inventory !== null && $variant->inventory->available() > 0,
        );
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec app php artisan test --filter=ProductCatalogModelTest`
Expected: PASS, all methods including the 5 new ones.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Product.php tests/Feature/Catalog/ProductCatalogModelTest.php
git commit -m "feat: add Product::scopePublished, displayPrice, isInStock"
```

---

### Task 2: `Store` model — `logo_url`/`banner_url` accessors, `toStorefrontArray()`

**Files:**
- Modify: `app/Models/Store.php`
- Test: `tests/Feature/Store/StoreModelTest.php` (new file)

**Interfaces:**
- Produces: `Store->logo_url: ?string`, `Store->banner_url: ?string` — computed, appended attributes, `null` when the source path is `null`, otherwise resolved via `Storage::disk('s3')->url(...)` (same pattern as `ProductImage::url()`).
- Produces: `Store->toStorefrontArray(): array{name: string, description: ?string, whatsappNumber: ?string, isOpen: bool, primaryColor: ?string, logoUrl: ?string, bannerUrl: ?string}`.
- Consumed by: Task 3 (`HomeController`) and Task 4 (`ProductController`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Store/StoreModelTest.php`:

```php
<?php

namespace Tests\Feature\Store;

use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class StoreModelTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_logo_and_banner_url_resolve_from_the_s3_disk_when_set(): void
    {
        Storage::fake('s3');
        [$tenant] = $this->createTenantWithOwner();
        $store = $tenant->store;
        $store->update(['logo_path' => 'stores/logo.jpg', 'banner_path' => 'stores/banner.jpg']);

        $this->assertSame(Storage::disk('s3')->url('stores/logo.jpg'), $store->logo_url);
        $this->assertSame(Storage::disk('s3')->url('stores/banner.jpg'), $store->banner_url);
    }

    public function test_logo_and_banner_url_are_null_when_not_set(): void
    {
        [$tenant] = $this->createTenantWithOwner();

        $this->assertNull($tenant->store->logo_url);
        $this->assertNull($tenant->store->banner_url);
    }

    public function test_to_storefront_array_shape(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $store = $tenant->store;
        $store->update([
            'name' => 'Dapur Ibu',
            'description' => 'Kue rumahan',
            'whatsapp_number' => '+6281234567890',
            'is_open' => true,
            'primary_color' => '#C2703D',
        ]);

        $this->assertSame([
            'name' => 'Dapur Ibu',
            'description' => 'Kue rumahan',
            'whatsappNumber' => '+6281234567890',
            'isOpen' => true,
            'primaryColor' => '#C2703D',
            'logoUrl' => null,
            'bannerUrl' => null,
        ], $store->toStorefrontArray());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=StoreModelTest`
Expected: FAIL — `Call to undefined method App\Models\Store::toStorefrontArray()`.

- [ ] **Step 3: Implement**

In `app/Models/Store.php`, add imports `use Illuminate\Database\Eloquent\Casts\Attribute;` and `use Illuminate\Support\Facades\Storage;`, add `protected $appends = ['logo_url', 'banner_url'];` as a class property, and add:

```php
    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path === null ? null : Storage::disk('s3')->url($this->logo_path),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->banner_path === null ? null : Storage::disk('s3')->url($this->banner_path),
        );
    }

    /**
     * @return array{name: string, description: ?string, whatsappNumber: ?string, isOpen: bool, primaryColor: ?string, logoUrl: ?string, bannerUrl: ?string}
     */
    public function toStorefrontArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'whatsappNumber' => $this->whatsapp_number,
            'isOpen' => $this->is_open,
            'primaryColor' => $this->primary_color,
            'logoUrl' => $this->logo_url,
            'bannerUrl' => $this->banner_url,
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec app php artisan test --filter=StoreModelTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Store.php tests/Feature/Store/StoreModelTest.php
git commit -m "feat: add Store logo_url/banner_url accessors and toStorefrontArray"
```

---

### Task 3: `HomeController` — wire published products into the home page

**Files:**
- Modify: `app/Http/Controllers/Storefront/HomeController.php`
- Test: `tests/Feature/Storefront/ProductListingTest.php` (new file)

**Interfaces:**
- Consumes: `Product::scopePublished()`, `Product->displayPrice()`, `Product->isInStock()` (Task 1), `Store->toStorefrontArray()` (Task 2).
- Produces: `storefront/home` Inertia page now receives `products: Array<{id: string, slug: string, name: string, imageUrl: string|null, price: {amount: number, isFrom: boolean}, inStock: boolean}>` in addition to the existing `store` prop.

**Note:** `resources/js/pages/storefront/home.tsx` currently only destructures `{ store }` — it will silently ignore the new `products` prop and keep rendering the "Katalog produk akan segera hadir di sini" placeholder text. That's expected here; a follow-up frontend plan rewrites this page to actually render the grid. This task's job is only to get correct data to the page.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Storefront/ProductListingTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app php artisan test --filter=ProductListingTest`
Expected: FAIL — `products` prop missing / assertions fail since `HomeController` doesn't pass it yet.

- [ ] **Step 3: Implement**

Replace `app/Http/Controllers/Storefront/HomeController.php` with:

```php
<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single "/" route serves two entirely different pages from the same
 * path, branching on whether ResolveTenantFromHostname resolved a tenant
 * for this request's Host header — see docs/TENANCY.md and the middleware's
 * docblock for why this isn't two separate route registrations.
 */
class HomeController extends Controller
{
    public function __invoke(TenantContext $tenantContext): Response
    {
        if (! $tenantContext->hasTenant()) {
            return Inertia::render('welcome');
        }

        $tenant = $tenantContext->tenant();
        $store = $tenant->store;

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->published()
            ->with([
                'images' => fn ($query) => $query->limit(1),
                'variants.inventory',
            ])
            ->get()
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'imageUrl' => $product->images->first()?->url,
                'price' => $product->displayPrice(),
                'inStock' => $product->isInStock(),
            ]);

        return Inertia::render('storefront/home', [
            'store' => $store->toStorefrontArray(),
            'products' => $products,
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec app php artisan test --filter=ProductListingTest`
Expected: PASS. Also re-run: `docker compose exec app php artisan test --filter=HostnameResolutionTest` — it asserts `store.name` on the same page and must keep passing.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Storefront/HomeController.php tests/Feature/Storefront/ProductListingTest.php
git commit -m "feat: list published products on the storefront home page"
```

---

### Task 4: `Storefront\ProductController::show`, `/produk/{slug}` route

**Files:**
- Create: `app/Http/Controllers/Storefront/ProductController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/storefront/products/show.tsx` (minimal — just enough to exist as a valid Inertia page target; the follow-up frontend plan replaces its content with the real detail-page design)
- Test: `tests/Feature/Storefront/ProductDetailTest.php` (new file)

**Interfaces:**
- Consumes: `Product::scopePublished()` (Task 1), `Store->toStorefrontArray()` (Task 2).
- Produces route `storefront.product` (`GET /produk/{slug}`), rendering `storefront/products/show` with props `store` (same shape as Task 3) and `product: {name: string, description: string|null, images: Array<{id: string, url: string}>, options: Array<{id: string, name: string, values: Array<{id: string, value: string}>}>, variants: Array<{id: string, price: number, salePrice: number|null, optionValueIds: string[], stock: number}>}`. On a missing/foreign/unpublished product, returns a plain 404 (`abort(404)`) — a branded 404 page is part of the follow-up frontend plan, not this one.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Storefront/ProductDetailTest.php`:

```php
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
    }

    public function test_it_404s_for_a_nonexistent_slug(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $hostname = $this->domainFor($tenant);

        $response = $this->get("http://{$hostname}/produk/tidak-ada");

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec app php artisan test --filter=ProductDetailTest`
Expected: FAIL — 404 (route doesn't exist yet) for every case, including the one that should succeed.

- [ ] **Step 3: Implement the controller**

Create `app/Http/Controllers/Storefront/ProductController.php`:

```php
<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(string $slug, TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->tenant();

        $product = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->published()
            ->with(['images', 'options.values', 'variants.optionValues', 'variants.inventory'])
            ->first();

        abort_if($product === null, 404);

        return Inertia::render('storefront/products/show', [
            'store' => $tenant->store->toStorefrontArray(),
            'product' => [
                'name' => $product->name,
                'description' => $product->description,
                'images' => $product->images->map(fn ($image): array => [
                    'id' => $image->id,
                    'url' => $image->url,
                ])->all(),
                'options' => $product->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'values' => $option->values->map(fn ($value): array => [
                        'id' => $value->id,
                        'value' => $value->value,
                    ])->all(),
                ])->all(),
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'price' => $variant->price,
                    'salePrice' => $variant->sale_price,
                    'optionValueIds' => $variant->optionValues->pluck('id')->all(),
                    'stock' => $variant->inventory?->available() ?? 0,
                ])->all(),
            ],
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, add the import `use App\Http\Controllers\Storefront\ProductController;` and, after the existing `Route::get('/', HomeController::class)->name('home');` line, add:

```php
Route::get('/produk/{slug}', [ProductController::class, 'show'])
    ->middleware('tenant.required')
    ->name('storefront.product');
```

- [ ] **Step 5: Add a minimal placeholder page**

Create `resources/js/pages/storefront/products/show.tsx`:

```tsx
import { Head } from '@inertiajs/react';

type Props = {
    store: { name: string };
    product: { name: string };
};

export default function ProductShow({ store, product }: Props) {
    return (
        <>
            <Head title={`${product.name} — ${store.name}`} />
            <div className="mx-auto max-w-2xl px-4 py-10">
                <p>{product.name}</p>
            </div>
        </>
    );
}
```

- [ ] **Step 6: Regenerate Wayfinder actions**

Run: `docker compose exec app php artisan wayfinder:generate`
Expected: creates `resources/js/actions/App/Http/Controllers/Storefront/ProductController.ts` (gitignored, no need to inspect further).

- [ ] **Step 7: Run tests to verify they pass**

Run: `docker compose exec app php artisan test --filter=ProductDetailTest`
Expected: PASS.

- [ ] **Step 8: Full regression check**

Run: `docker compose exec app php artisan test`
Expected: PASS — no regressions in any previously-passing test.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Storefront/ProductController.php routes/web.php resources/js/pages/storefront/products/show.tsx tests/Feature/Storefront/ProductDetailTest.php
git commit -m "feat: add storefront product detail route and controller"
```

---

## Next

Once this lands, the follow-up plan against `docs/superpowers/specs/2026-07-31-storefront-visual-design.md` covers: Fraunces/Figtree fonts, the `.storefront` CSS token system, OKLCH tenant-color derivation, `StorefrontLayout`/`ProductCard` components, the real home-page and product-detail-page designs, the option picker, and the branded 404 page.
