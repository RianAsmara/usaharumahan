# Phase 2 Dashboard Catalog & Inventory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the owner/staff dashboard half of Phase 2 — categories, products, images, options/variants, and the inventory ledger — per `docs/superpowers/specs/2026-07-29-phase2-catalog-inventory-design.md`.

**Architecture:** Follows the existing modular-monolith conventions from Phase 1 (`docs/ARCHITECTURE.md`): ULID-keyed, tenant-scoped Eloquent models; thin Inertia dashboard controllers that validate via Form Requests, authorize via Policies, and delegate multi-step/transactional work to single-purpose Action classes; React/Inertia pages using the generated Wayfinder `.form()` helpers, matching `resources/js/pages/dashboard/store/edit.tsx`.

**Tech Stack:** Laravel 13 (PHP 8.3), PostgreSQL 16, Pest 4, Inertia 3 + React 19 + TypeScript, shadcn/ui (Dialog, Select not used — plain `<select>` for form-embedded pickers), Laravel Wayfinder for typed routes/actions.

## Global Constraints

- PostgreSQL only, real DB in tests (no SQLite) — `docs/DATABASE.md` §1.
- Every new table gets a ULID primary key (`$table->ulid('id')->primary()`) except the 1:1 `inventories` table, whose primary key **is** `product_variant_id` (same pattern as `stores.tenant_id`), and the `product_variant_option_values` pivot, whose primary key is the composite pair.
- Every tenant-owned table carries a non-null `tenant_id` — `docs/DATABASE.md` §1, §7.
- Money and stock-quantity columns that must never go negative use `unsignedBigInteger`/`unsignedInteger` (Laravel's Postgres grammar adds a `CHECK (col >= 0)` constraint for these) — never raw SQL, never `float` — `docs/DATABASE.md` §6.
- `unique(tenant_id, slug)` / `unique(tenant_id, sku)` are DB-level constraints, not just validation — `docs/DATABASE.md` §4.
- Every Policy re-derives tenant membership from the model's own `tenant_id`, independent of how the model was fetched — never trust `TenantContext` alone inside a Policy — `docs/TENANCY.md` §5, matching `App\Policies\StorePolicy::membershipFor()`.
- Every Form Request's `authorize()` returns `true`; the controller calls `Gate::authorize(...)` explicitly once it has the tenant/model in hand — matching `UpdateStoreRequest` / `StoreController`.
- `Storage::fake('s3')` for every file-upload test; the app's default disk is `s3` (MinIO locally, per `.env.example`) — `docs/TESTING.md` §4.
- Run every backend command inside the app container: `docker compose exec app <command>`. Run every `npm` command the same way.
- Simple products (no options) always get exactly one auto-created variant — there is never a product with zero variants or a price stored anywhere but `product_variants.price`.
- Max 6 images per product, 4MB each, `jpg`/`jpeg`/`png`/`webp` only. Max 3 options per product, max 20 values per option.
- Staff can view the full catalog and adjust inventory; only owners can create/update/delete categories, products, options, variants, and images.

---

## File Structure

New backend files, grouped by the task that creates them:

```
app/Support/Catalog/UniqueSlug.php
app/Enums/ProductStatus.php
app/Enums/InventoryMovementType.php
app/Models/{Category,Product,ProductImage,ProductOption,ProductOptionValue,ProductVariant,Inventory,InventoryMovement}.php
database/factories/{Category,Product,ProductImage,ProductOption,ProductOptionValue,ProductVariant,Inventory,InventoryMovement}Factory.php
database/migrations/2026_07_29_2000{00..08}_*.php  (9 migrations, one per table)
app/Policies/{Category,Product,Inventory}Policy.php
app/Actions/Catalog/{CreateProduct,AddProductVariant}.php
app/Actions/Inventory/AdjustInventory.php
app/Http/Requests/Catalog/{CreateCategoryRequest,UpdateCategoryRequest,CreateProductRequest,UpdateProductRequest,CreateProductOptionRequest,CreateProductVariantRequest,UpdateProductVariantRequest,UploadProductImageRequest,ReorderProductImagesRequest}.php
app/Http/Requests/Inventory/AdjustInventoryRequest.php
app/Http/Controllers/Dashboard/{CategoryController,ProductController,ProductOptionController,ProductVariantController,ProductImageController,InventoryController}.php
```

Modified: `routes/dashboard.php` (incrementally, one task at a time), `app/Providers/AppServiceProvider.php` (registers `InventoryPolicy` for `ProductVariant`, since the ability's subject model doesn't match the policy's name for Laravel's auto-discovery).

New frontend files:

```
resources/js/pages/dashboard/categories/index.tsx
resources/js/pages/dashboard/products/index.tsx
resources/js/pages/dashboard/products/create.tsx
resources/js/pages/dashboard/products/edit.tsx
```

`resources/js/actions/**` and `resources/js/routes/**` are Wayfinder-generated (gitignored) — they regenerate automatically from `routes/dashboard.php` whenever `npm run dev`/`npm run build` runs. Nothing in this plan hand-writes them.

---

### Task 1: `UniqueSlug` helper + Category schema/model/factory

**Files:**
- Create: `app/Support/Catalog/UniqueSlug.php`
- Create: `database/migrations/2026_07_29_200000_create_categories_table.php`
- Create: `app/Models/Category.php`
- Create: `database/factories/CategoryFactory.php`
- Test: `tests/Feature/Catalog/CategoryModelTest.php`

**Interfaces:**
- Produces: `App\Support\Catalog\UniqueSlug::generate(string $table, string $tenantId, string $source, string $column = 'slug', ?string $ignoreId = null): string` — reused by Tasks 2, 4, 6, 7 for category slugs, product slugs, and variant SKUs.
- Produces: `App\Models\Category` with fillable `tenant_id, name, slug, description, is_active, sort_order`, a `tenant()` BelongsTo, and a `products()` HasMany (the inverse is added to `Product` in Task 4).
- Produces: `Database\Factories\CategoryFactory`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=CategoryModelTest`
Expected: FAIL — `Class "App\Models\Category" not found` (or similar).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

- [ ] **Step 4: Write the `UniqueSlug` helper**

```php
<?php

namespace App\Support\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns a name into a URL/SKU-safe identifier that is unique within a
 * tenant, appending "-2", "-3", ... on collision. Used for category slugs,
 * product slugs, and variant SKUs — anywhere the schema enforces
 * `unique(tenant_id, <column>)`.
 */
class UniqueSlug
{
    public static function generate(
        string $table,
        string $tenantId,
        string $source,
        string $column = 'slug',
        ?string $ignoreId = null,
    ): string {
        $base = Str::slug($source);
        $candidate = $base;
        $suffix = 2;

        while (
            DB::table($table)
                ->where('tenant_id', $tenantId)
                ->where($column, $candidate)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
```

- [ ] **Step 5: Write the `Category` model**

```php
<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['tenant_id', 'name', 'slug', 'description', 'is_active', 'sort_order'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

- [ ] **Step 6: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
```

- [ ] **Step 7: Run migrations and the test**

Run: `docker compose exec app php artisan migrate && docker compose exec app php artisan test --filter=CategoryModelTest`
Expected: PASS — 4 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Support/Catalog/UniqueSlug.php app/Models/Category.php database/factories/CategoryFactory.php database/migrations/2026_07_29_200000_create_categories_table.php tests/Feature/Catalog/CategoryModelTest.php
git commit -m "feat: add category schema, model, and unique-slug helper"
```

---

### Task 2: CategoryPolicy + CategoryController + Form Requests + routes + HTTP tests

**Files:**
- Create: `app/Policies/CategoryPolicy.php`
- Create: `app/Http/Requests/Catalog/CreateCategoryRequest.php`
- Create: `app/Http/Requests/Catalog/UpdateCategoryRequest.php`
- Create: `app/Http/Controllers/Dashboard/CategoryController.php`
- Modify: `routes/dashboard.php`
- Test: `tests/Feature/Catalog/CategoryManagementTest.php`

**Interfaces:**
- Consumes: `Category` model + `CategoryFactory` (Task 1), `UniqueSlug::generate()` (Task 1), `TenantContext::tenant()` (existing), `Tests\Concerns\CreatesTenants::createTenantWithOwner()/addStaffToTenant()` (existing).
- Produces: named routes `categories.index`, `categories.store`, `categories.update`, `categories.destroy`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_a_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();

        $response = $this->actingAs($owner)->post(route('categories.store'), [
            'name' => 'Makanan Ringan',
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'tenant_id' => $tenant->id,
            'name' => 'Makanan Ringan',
            'slug' => 'makanan-ringan',
        ]);
    }

    public function test_owner_can_update_a_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->put(route('categories.update', $category), [
            'name' => 'Kue Kering',
            'is_active' => false,
        ]);

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Kue Kering',
            'is_active' => false,
        ]);
    }

    public function test_owner_can_delete_a_category(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->delete(route('categories.destroy', $category));

        $response->assertRedirect(route('categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_staff_cannot_create_update_or_delete_categories(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $category = Category::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($staff)->post(route('categories.store'), ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($staff)->put(route('categories.update', $category), ['name' => 'Diubah'])->assertForbidden();
        $this->actingAs($staff)->delete(route('categories.destroy', $category))->assertForbidden();

        $this->assertDatabaseMissing('categories', ['name' => 'Baru']);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => $category->name]);
    }

    public function test_staff_can_still_view_the_category_list(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        Category::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($staff)->get(route('categories.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('categories', 1)
            ->where('can.create', false));
    }

    public function test_a_tenant_never_sees_another_tenants_categories(): void
    {
        [$tenantA, $ownerA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();
        Category::factory()->create(['tenant_id' => $tenantB->id, 'name' => 'Milik Tenant B']);

        $response = $this->actingAs($ownerA)->get(route('categories.index'));

        $response->assertInertia(fn ($page) => $page->has('categories', 0));
    }

    public function test_an_owner_cannot_update_another_tenants_category(): void
    {
        [, $ownerA] = $this->createTenantWithOwner();
        [$tenantB] = $this->createTenantWithOwner();
        $categoryB = Category::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($ownerA)->put(route('categories.update', $categoryB), ['name' => 'Dibajak'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=CategoryManagementTest`
Expected: FAIL — route `categories.store` not defined.

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

use App\Enums\TenantMembershipRole;
use App\Models\Category;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenantMemberships()->exists();
    }

    public function view(User $user, Category $category): bool
    {
        return $this->membershipFor($user, $category) !== null;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantMembershipRole::Owner)
            ->exists();
    }

    public function update(User $user, Category $category): bool
    {
        return $this->membershipFor($user, $category)?->role === TenantMembershipRole::Owner;
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }

    private function membershipFor(User $user, Category $category): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $category->tenant_id)
            ->first();
    }
}
```

- [ ] **Step 4: Write the Form Requests**

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // CategoryController::store() authorizes explicitly once it knows
        // the current tenant — see StoreController::update() for the same
        // pattern.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama kategori wajib diisi.',
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateCategoryRequest;
use App\Http\Requests\Catalog\UpdateCategoryRequest;
use App\Models\Category;
use App\Support\Catalog\UniqueSlug;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->tenant();

        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('dashboard/categories/index', [
            'categories' => $categories,
            'can' => [
                'create' => Gate::allows('create', [Category::class, $tenant]),
            ],
        ]);
    }

    public function store(CreateCategoryRequest $request, TenantContext $tenantContext): RedirectResponse
    {
        $tenant = $tenantContext->tenant();

        Gate::authorize('create', [Category::class, $tenant]);

        Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $request->string('name')->toString(),
            'slug' => UniqueSlug::generate('categories', $tenant->id, $request->string('name')->toString()),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        $category->update([
            'name' => $request->string('name')->toString(),
            'slug' => UniqueSlug::generate(
                'categories',
                $category->tenant_id,
                $request->string('name')->toString(),
                ignoreId: $category->id,
            ),
            'description' => $request->input('description'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return redirect()->route('categories.index');
    }
}
```

- [ ] **Step 6: Add routes**

Modify `routes/dashboard.php` — add the import and, inside the existing `Route::middleware(['auth', 'verified', 'tenant.dashboard'])->group(function () { ... })` block (after the `store.*` routes), add:

```php
use App\Http\Controllers\Dashboard\CategoryController;

Route::get('dashboard/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::post('dashboard/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('dashboard/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('dashboard/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
```

- [ ] **Step 7: Run the test**

Run: `docker compose exec app php artisan test --filter=CategoryManagementTest`
Expected: PASS — 7 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Policies/CategoryPolicy.php app/Http/Requests/Catalog/CreateCategoryRequest.php app/Http/Requests/Catalog/UpdateCategoryRequest.php app/Http/Controllers/Dashboard/CategoryController.php routes/dashboard.php tests/Feature/Catalog/CategoryManagementTest.php
git commit -m "feat: add category dashboard CRUD with owner-only writes"
```

---

### Task 3: Frontend — `dashboard/categories/index.tsx`

**Files:**
- Create: `resources/js/pages/dashboard/categories/index.tsx`

**Interfaces:**
- Consumes: `categories.*` routes (Task 2) via the generated `@/actions/App/Http/Controllers/Dashboard/CategoryController`; `Heading`, `InputError`, `Button`, `Checkbox`, `Input`, `Label`, `Spinner` (existing shared components, see `resources/js/pages/dashboard/store/edit.tsx`); `Dialog`/`DialogContent`/`DialogFooter`/`DialogHeader`/`DialogTitle`/`DialogTrigger` (existing `resources/js/components/ui/dialog.tsx`, unused elsewhere so far — this is its first consumer).

- [ ] **Step 1: Write the page**

```tsx
import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Dashboard/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Category = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

type Props = {
    categories: Category[];
    can: {
        create: boolean;
    };
};

export default function CategoriesIndex({ categories, can }: Props) {
    const [editing, setEditing] = useState<Category | null>(null);
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Kategori" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Kategori"
                        description="Kelompokkan produk Anda agar mudah ditemukan pelanggan"
                    />

                    {can.create && (
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button>Tambah kategori</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Kategori baru</DialogTitle>
                                </DialogHeader>
                                <Form
                                    {...CategoryController.store.form()}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setCreating(false)}
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Nama kategori
                                                </Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                    autoFocus
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="description">
                                                    Deskripsi
                                                </Label>
                                                <textarea
                                                    id="description"
                                                    name="description"
                                                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                                />
                                                <InputError
                                                    message={
                                                        errors.description
                                                    }
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="is_active"
                                                    name="is_active"
                                                    defaultChecked
                                                />
                                                <Label
                                                    htmlFor="is_active"
                                                    className="font-normal"
                                                >
                                                    Aktif
                                                </Label>
                                            </div>
                                            <DialogFooter>
                                                <Button type="submit">
                                                    {processing && (
                                                        <Spinner />
                                                    )}
                                                    Simpan
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {categories.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada kategori.
                    </p>
                )}

                <ul className="divide-y divide-border rounded-md border">
                    {categories.map((category) => (
                        <li
                            key={category.id}
                            className="flex items-center justify-between gap-4 p-4"
                        >
                            <div>
                                <p className="font-medium">{category.name}</p>
                                {category.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {category.description}
                                    </p>
                                )}
                                {!category.is_active && (
                                    <p className="text-xs text-muted-foreground">
                                        Nonaktif
                                    </p>
                                )}
                            </div>

                            {can.create && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditing(category)}
                                    >
                                        Ubah
                                    </Button>
                                    <Form
                                        {...CategoryController.destroy.form(
                                            category.id,
                                        )}
                                        onBefore={() =>
                                            confirm(
                                                `Hapus kategori "${category.name}"?`,
                                            )
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Hapus
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ubah kategori</DialogTitle>
                    </DialogHeader>
                    {editing && (
                        <Form
                            {...CategoryController.update.form(editing.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-name">
                                            Nama kategori
                                        </Label>
                                        <Input
                                            id="edit-name"
                                            name="name"
                                            required
                                            defaultValue={editing.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-description">
                                            Deskripsi
                                        </Label>
                                        <textarea
                                            id="edit-description"
                                            name="description"
                                            defaultValue={
                                                editing.description ?? ''
                                            }
                                            className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="edit-is_active"
                                            name="is_active"
                                            defaultChecked={editing.is_active}
                                        />
                                        <Label
                                            htmlFor="edit-is_active"
                                            className="font-normal"
                                        >
                                            Aktif
                                        </Label>
                                    </div>
                                    <DialogFooter>
                                        <Button type="submit">
                                            {processing && <Spinner />}
                                            Simpan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
```

- [ ] **Step 2: Regenerate Wayfinder types and type-check**

Run: `docker compose exec app npm run types:check`
Expected: PASS. If `CategoryController.destroy.form(category.id)` or `.update.form(editing.id)` don't match the generated signature in `resources/js/actions/App/Http/Controllers/Dashboard/CategoryController.ts` (regenerated automatically by the Vite Wayfinder plugin), open that generated file and adjust the call to match its actual exported shape.

- [ ] **Step 3: Lint and format**

Run: `docker compose exec app npm run lint:check && docker compose exec app npm run format:check`
Expected: PASS. Fix with `docker compose exec app npm run format` if Prettier complains.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/dashboard/categories/index.tsx
git commit -m "feat: add category dashboard page"
```

---

### Task 4: Product core schema — products, images, options, option values, variants, variant↔option-value pivot

**Files:**
- Create: `app/Enums/ProductStatus.php`
- Create: `database/migrations/2026_07_29_200100_create_products_table.php`
- Create: `database/migrations/2026_07_29_200200_create_product_images_table.php`
- Create: `database/migrations/2026_07_29_200300_create_product_options_table.php`
- Create: `database/migrations/2026_07_29_200400_create_product_option_values_table.php`
- Create: `database/migrations/2026_07_29_200500_create_product_variants_table.php`
- Create: `database/migrations/2026_07_29_200600_create_product_variant_option_values_table.php`
- Create: `app/Models/{Product,ProductImage,ProductOption,ProductOptionValue,ProductVariant}.php`
- Create: `database/factories/{Product,ProductImage,ProductOption,ProductOptionValue,ProductVariant}Factory.php`
- Test: `tests/Feature/Catalog/ProductCatalogModelTest.php`

**Interfaces:**
- Consumes: `Category` (Task 1) for `Product::category()`.
- Produces: `App\Enums\ProductStatus` (`Draft`, `Active`, `Archived`); `Product` with `tenant()`, `category()`, `images()`, `options()`, `variants()`; `ProductOption::values()`; `ProductOptionValue::option()`, `::variants()` (BelongsToMany through the pivot); `ProductVariant::product()`, `::optionValues()` (BelongsToMany), `::inventory()` (HasOne — the `Inventory` model itself is added in Task 5, so this relation is defined here but only usable once Task 5 lands). All factories, consumed by every later task.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
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
        $value = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'M']);
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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=ProductCatalogModelTest`
Expected: FAIL — `Class "App\Models\Product" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
```

- [ ] **Step 4: Write the migrations**

`database/migrations/2026_07_29_200100_create_products_table.php`:

```php
<?php

use App\Enums\ProductStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status')->default(ProductStatus::Draft->value);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
```

`database/migrations/2026_07_29_200200_create_product_images_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
```

`database/migrations/2026_07_29_200300_create_product_options_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_options');
    }
};
```

`database/migrations/2026_07_29_200400_create_product_option_values_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_option_values', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_option_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_option_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_option_values');
    }
};
```

`database/migrations/2026_07_29_200500_create_product_variants_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'sku']);
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
```

`database/migrations/2026_07_29_200600_create_product_variant_option_values_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_option_values', function (Blueprint $table) {
            $table->foreignUlid('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_option_value_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_variant_id', 'product_option_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_values');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/Product.php`:

```php
<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property ProductStatus $status
 * @property Carbon|null $published_at
 */
#[Fillable(['tenant_id', 'category_id', 'name', 'slug', 'description', 'status', 'published_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

`app/Models/ProductImage.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string $path
 * @property int $sort_order
 */
#[Fillable(['tenant_id', 'product_id', 'path', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
```

`app/Models/ProductOption.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ProductOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string $name
 * @property int $sort_order
 */
#[Fillable(['tenant_id', 'product_id', 'name', 'sort_order'])]
class ProductOption extends Model
{
    /** @use HasFactory<ProductOptionFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<ProductOptionValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(ProductOptionValue::class)->orderBy('sort_order');
    }
}
```

`app/Models/ProductOptionValue.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ProductOptionValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $product_option_id
 * @property string $value
 * @property int $sort_order
 */
#[Fillable(['product_option_id', 'value', 'sort_order'])]
class ProductOptionValue extends Model
{
    /** @use HasFactory<ProductOptionValueFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<ProductOption, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }

    /**
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'product_variant_option_values');
    }
}
```

`app/Models/ProductVariant.php`:

```php
<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string $sku
 * @property int $price
 * @property int|null $sale_price
 * @property int|null $weight_grams
 */
#[Fillable(['tenant_id', 'product_id', 'sku', 'price', 'sale_price', 'weight_grams'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, HasUlids;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsToMany<ProductOptionValue, $this>
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(ProductOptionValue::class, 'product_variant_option_values');
    }

    /**
     * @return HasOne<Inventory, $this>
     */
    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }
}
```

- [ ] **Step 6: Write the factories**

`database/factories/ProductFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => ProductStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Active,
            'published_at' => now(),
        ]);
    }
}
```

`database/factories/ProductImageFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'path' => 'products/'.fake()->uuid().'.jpg',
            'sort_order' => 0,
        ];
    }
}
```

`database/factories/ProductOptionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOption>
 */
class ProductOptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Ukuran', 'Warna', 'Rasa']),
            'sort_order' => 0,
        ];
    }
}
```

`database/factories/ProductOptionValueFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionValue>
 */
class ProductOptionValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_option_id' => ProductOption::factory(),
            'value' => fake()->randomElement(['S', 'M', 'L']),
            'sort_order' => 0,
        ];
    }
}
```

`database/factories/ProductVariantFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.fake()->unique()->numerify('######'),
            'price' => fake()->numberBetween(10000, 500000),
        ];
    }
}
```

- [ ] **Step 7: Run migrations and the test**

Run: `docker compose exec app php artisan migrate && docker compose exec app php artisan test --filter=ProductCatalogModelTest`
Expected: PASS — 2 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/ProductStatus.php app/Models/Product.php app/Models/ProductImage.php app/Models/ProductOption.php app/Models/ProductOptionValue.php app/Models/ProductVariant.php database/factories/ProductFactory.php database/factories/ProductImageFactory.php database/factories/ProductOptionFactory.php database/factories/ProductOptionValueFactory.php database/factories/ProductVariantFactory.php database/migrations/2026_07_29_200100_create_products_table.php database/migrations/2026_07_29_200200_create_product_images_table.php database/migrations/2026_07_29_200300_create_product_options_table.php database/migrations/2026_07_29_200400_create_product_option_values_table.php database/migrations/2026_07_29_200500_create_product_variants_table.php database/migrations/2026_07_29_200600_create_product_variant_option_values_table.php tests/Feature/Catalog/ProductCatalogModelTest.php
git commit -m "feat: add product, image, option, and variant schema"
```

---

### Task 5: Inventory schema — inventories + inventory_movements

**Files:**
- Create: `app/Enums/InventoryMovementType.php`
- Create: `database/migrations/2026_07_29_200700_create_inventories_table.php`
- Create: `database/migrations/2026_07_29_200800_create_inventory_movements_table.php`
- Create: `app/Models/{Inventory,InventoryMovement}.php`
- Create: `database/factories/{Inventory,InventoryMovement}Factory.php`
- Test: `tests/Feature/Inventory/InventoryModelTest.php`

**Interfaces:**
- Consumes: `ProductVariant` (Task 4).
- Produces: `App\Enums\InventoryMovementType` (`Restock`, `Adjustment`, `Sale`, `Return`); `Inventory` (primary key `product_variant_id`) with `variant()` and `available(): int`; `InventoryMovement` with `variant()`, `actor()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class InventoryModelTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_variant_has_one_inventory_row(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 5]);

        $this->assertSame(5, $variant->inventory->on_hand);
        $this->assertSame(5, $variant->inventory->available());
    }

    public function test_available_stock_subtracts_reserved_from_on_hand(): void
    {
        $inventory = Inventory::factory()->make(['on_hand' => 10, 'reserved' => 3]);

        $this->assertSame(7, $inventory->available());
    }

    public function test_a_movement_records_the_resulting_on_hand_snapshot(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);

        $movement = InventoryMovement::factory()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'quantity_delta' => 10,
            'resulting_on_hand' => 10,
        ]);

        $this->assertTrue($movement->variant->is($variant));
        $this->assertNull($movement->actor);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=InventoryModelTest`
Expected: FAIL — `Class "App\Models\Inventory" not found`.

- [ ] **Step 3: Write the enum**

```php
<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Restock = 'restock';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Return = 'return';
}
```

- [ ] **Step 4: Write the migrations**

`database/migrations/2026_07_29_200700_create_inventories_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->foreignUlid('product_variant_id')->primary()->constrained()->cascadeOnDelete();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('on_hand')->default(0);
            $table->unsignedInteger('reserved')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
```

`database/migrations/2026_07_29_200800_create_inventory_movements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->integer('quantity_delta');
            $table->integer('resulting_on_hand');
            $table->text('note')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/Inventory.php`:

```php
<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $product_variant_id
 * @property string $tenant_id
 * @property int $on_hand
 * @property int $reserved
 */
#[Fillable(['product_variant_id', 'tenant_id', 'on_hand', 'reserved'])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $primaryKey = 'product_variant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function available(): int
    {
        return $this->on_hand - $this->reserved;
    }
}
```

`app/Models/InventoryMovement.php`:

```php
<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_variant_id
 * @property InventoryMovementType $type
 * @property int $quantity_delta
 * @property int $resulting_on_hand
 * @property string|null $note
 * @property int|null $actor_user_id
 */
#[Fillable(['tenant_id', 'product_variant_id', 'type', 'quantity_delta', 'resulting_on_hand', 'note', 'actor_user_id'])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
```

- [ ] **Step 6: Write the factories**

`database/factories/InventoryFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'on_hand' => 0,
            'reserved' => 0,
        ];
    }
}
```

`database/factories/InventoryMovementFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'type' => InventoryMovementType::Restock,
            'quantity_delta' => 10,
            'resulting_on_hand' => 10,
        ];
    }
}
```

- [ ] **Step 7: Run migrations and the test**

Run: `docker compose exec app php artisan migrate && docker compose exec app php artisan test --filter=InventoryModelTest`
Expected: PASS — 3 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Enums/InventoryMovementType.php app/Models/Inventory.php app/Models/InventoryMovement.php database/factories/InventoryFactory.php database/factories/InventoryMovementFactory.php database/migrations/2026_07_29_200700_create_inventories_table.php database/migrations/2026_07_29_200800_create_inventory_movements_table.php tests/Feature/Inventory/InventoryModelTest.php
git commit -m "feat: add inventory and inventory movement schema"
```

---

### Task 6: `CreateProduct` action

**Files:**
- Create: `app/Actions/Catalog/CreateProduct.php`
- Test: `tests/Feature/Catalog/CreateProductActionTest.php`

**Interfaces:**
- Consumes: `Product`, `ProductVariant`, `Inventory` (Tasks 4–5), `UniqueSlug::generate()` (Task 1).
- Produces: `App\Actions\Catalog\CreateProduct::handle(Tenant $tenant, string $name, ?string $categoryId, ?string $description, int $price): Product` — consumed by Task 9's `ProductController::store()`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=CreateProductActionTest`
Expected: FAIL — `Class "App\Actions\Catalog\CreateProduct" not found`.

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Actions\Catalog;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Support\Catalog\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product together with the one variant every product must have
 * (see docs/superpowers/specs/2026-07-29-phase2-catalog-inventory-design.md
 * §2) — a simple product with no options gets a single auto-named variant
 * transparently, so there is never a product without a price or a stock
 * row.
 */
class CreateProduct
{
    public function handle(
        Tenant $tenant,
        string $name,
        ?string $categoryId,
        ?string $description,
        int $price,
    ): Product {
        return DB::transaction(function () use ($tenant, $name, $categoryId, $description, $price) {
            $product = Product::query()->create([
                'tenant_id' => $tenant->id,
                'category_id' => $categoryId,
                'name' => $name,
                'slug' => UniqueSlug::generate('products', $tenant->id, $name),
                'description' => $description,
            ]);

            $variant = ProductVariant::query()->create([
                'tenant_id' => $tenant->id,
                'product_id' => $product->id,
                'sku' => strtoupper(UniqueSlug::generate('product_variants', $tenant->id, $name, 'sku')),
                'price' => $price,
            ]);

            Inventory::query()->create([
                'tenant_id' => $tenant->id,
                'product_variant_id' => $variant->id,
                'on_hand' => 0,
                'reserved' => 0,
            ]);

            return $product;
        });
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec app php artisan test --filter=CreateProductActionTest`
Expected: PASS — 2 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Catalog/CreateProduct.php tests/Feature/Catalog/CreateProductActionTest.php
git commit -m "feat: add CreateProduct action with auto default variant"
```

---

### Task 7: `AddProductVariant` action

**Files:**
- Create: `app/Actions/Catalog/AddProductVariant.php`
- Test: `tests/Feature/Catalog/AddProductVariantActionTest.php`

**Interfaces:**
- Consumes: `ProductVariant`, `Inventory` (Tasks 4–5), `UniqueSlug::generate()` (Task 1).
- Produces: `App\Actions\Catalog\AddProductVariant::handle(Product $product, int $price, ?int $salePrice, ?int $weightGrams, array $optionValueIds, ?string $skuSuffix): ProductVariant` — consumed by Task 10's `ProductVariantController::store()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\AddProductVariant;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AddProductVariantActionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_it_adds_a_variant_with_option_values_and_zero_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kaos Polos']);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'name' => 'Ukuran']);
        $valueM = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'M']);

        $variant = app(AddProductVariant::class)->handle(
            product: $product,
            price: 50000,
            salePrice: null,
            weightGrams: 200,
            optionValueIds: [$valueM->id],
            skuSuffix: 'M',
        );

        $this->assertSame(50000, $variant->price);
        $this->assertTrue($variant->optionValues->first()->is($valueM));
        $this->assertSame(0, $variant->inventory->on_hand);
        $this->assertStringContainsString('KAOS-POLOS-M', $variant->sku);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=AddProductVariantActionTest`
Expected: FAIL — `Class "App\Actions\Catalog\AddProductVariant" not found`.

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Actions\Catalog;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Catalog\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Adds an additional variant to an existing product (e.g. the "M" size
 * after "S" already exists), wiring up its option-value combination and a
 * zero-stock inventory row in one transaction.
 */
class AddProductVariant
{
    /**
     * @param  array<int, string>  $optionValueIds
     */
    public function handle(
        Product $product,
        int $price,
        ?int $salePrice,
        ?int $weightGrams,
        array $optionValueIds,
        ?string $skuSuffix,
    ): ProductVariant {
        return DB::transaction(function () use ($product, $price, $salePrice, $weightGrams, $optionValueIds, $skuSuffix) {
            $variant = ProductVariant::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_id' => $product->id,
                'sku' => strtoupper(UniqueSlug::generate(
                    'product_variants',
                    $product->tenant_id,
                    $skuSuffix !== null ? "{$product->name}-{$skuSuffix}" : $product->name,
                    'sku',
                )),
                'price' => $price,
                'sale_price' => $salePrice,
                'weight_grams' => $weightGrams,
            ]);

            if ($optionValueIds !== []) {
                $variant->optionValues()->attach($optionValueIds);
            }

            Inventory::query()->create([
                'tenant_id' => $product->tenant_id,
                'product_variant_id' => $variant->id,
                'on_hand' => 0,
                'reserved' => 0,
            ]);

            return $variant;
        });
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec app php artisan test --filter=AddProductVariantActionTest`
Expected: PASS — 1 test.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Catalog/AddProductVariant.php tests/Feature/Catalog/AddProductVariantActionTest.php
git commit -m "feat: add AddProductVariant action"
```

---

### Task 8: `AdjustInventory` action

**Files:**
- Create: `app/Actions/Inventory/AdjustInventory.php`
- Test: `tests/Feature/Inventory/AdjustInventoryActionTest.php`

**Interfaces:**
- Consumes: `Inventory`, `InventoryMovement`, `InventoryMovementType` (Task 5).
- Produces: `App\Actions\Inventory\AdjustInventory::handle(ProductVariant $variant, InventoryMovementType $type, int $quantityDelta, ?string $note, User $actor): InventoryMovement` (throws `RuntimeException` if the result would go negative) — consumed by Task 12's `InventoryController::store()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AdjustInventoryActionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_restock_increases_on_hand_and_records_a_movement(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 5]);

        $movement = app(AdjustInventory::class)->handle(
            variant: $variant,
            type: InventoryMovementType::Restock,
            quantityDelta: 20,
            note: 'Kiriman baru',
            actor: $owner,
        );

        $this->assertSame(25, $movement->resulting_on_hand);
        $this->assertSame(25, $variant->inventory()->first()->on_hand);
        $this->assertSame($owner->id, $movement->actor_user_id);
    }

    public function test_sequential_adjustments_accumulate_correctly_from_fresh_state(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 0]);

        app(AdjustInventory::class)->handle($variant, InventoryMovementType::Restock, 10, null, $owner);
        app(AdjustInventory::class)->handle($variant, InventoryMovementType::Adjustment, -3, 'Rusak', $owner);
        $last = app(AdjustInventory::class)->handle($variant, InventoryMovementType::Restock, 5, null, $owner);

        $this->assertSame(12, $last->resulting_on_hand);
        $this->assertSame(12, $variant->inventory()->first()->on_hand);
        $this->assertSame(3, InventoryMovement::query()->where('product_variant_id', $variant->id)->count());
    }

    public function test_it_refuses_to_let_stock_go_negative(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 2]);

        $this->expectException(RuntimeException::class);

        app(AdjustInventory::class)->handle($variant, InventoryMovementType::Adjustment, -5, null, $owner);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=AdjustInventoryActionTest`
Expected: FAIL — `Class "App\Actions\Inventory\AdjustInventory" not found`.

- [ ] **Step 3: Write the action**

```php
<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a stock movement and updates on_hand, row-locking the variant's
 * inventory row for the duration of the transaction so two adjustments can
 * never read-then-write a stale on_hand — see docs/DATABASE.md §8. True
 * concurrent-request locking is exercised in Phase 4's checkout reservation
 * tests (docs/TESTING.md §3); this action's own tests prove sequential
 * correctness and that on_hand never goes negative.
 */
class AdjustInventory
{
    public function handle(
        ProductVariant $variant,
        InventoryMovementType $type,
        int $quantityDelta,
        ?string $note,
        User $actor,
    ): InventoryMovement {
        return DB::transaction(function () use ($variant, $type, $quantityDelta, $note, $actor) {
            /** @var Inventory $inventory */
            $inventory = Inventory::query()
                ->whereKey($variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $resultingOnHand = $inventory->on_hand + $quantityDelta;

            if ($resultingOnHand < 0) {
                throw new RuntimeException('Stok tidak boleh menjadi negatif.');
            }

            $inventory->update(['on_hand' => $resultingOnHand]);

            return InventoryMovement::query()->create([
                'tenant_id' => $variant->tenant_id,
                'product_variant_id' => $variant->id,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'resulting_on_hand' => $resultingOnHand,
                'note' => $note,
                'actor_user_id' => $actor->id,
            ]);
        });
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec app php artisan test --filter=AdjustInventoryActionTest`
Expected: PASS — 3 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Inventory/AdjustInventory.php tests/Feature/Inventory/AdjustInventoryActionTest.php
git commit -m "feat: add AdjustInventory action with row-locked stock updates"
```

---

### Task 9: ProductPolicy + ProductController (full CRUD) + Form Requests + routes + tests

**Files:**
- Create: `app/Policies/ProductPolicy.php`
- Create: `app/Http/Requests/Catalog/CreateProductRequest.php`
- Create: `app/Http/Requests/Catalog/UpdateProductRequest.php`
- Create: `app/Http/Controllers/Dashboard/ProductController.php`
- Modify: `routes/dashboard.php`
- Test: `tests/Feature/Catalog/ProductManagementTest.php`

**Interfaces:**
- Consumes: `CreateProduct` action (Task 6), `Category` (Task 1), `Product`/`ProductStatus` (Task 4).
- Produces: named routes `products.index`, `products.create`, `products.store`, `products.edit`, `products.update`, `products.destroy`.

- [ ] **Step 1: Write the failing test**

```php
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
        $this->actingAs($ownerA)->put(route('products.update', $productB), ['name' => 'x', 'status' => ProductStatus::Draft->value])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=ProductManagementTest`
Expected: FAIL — route `products.store` not defined.

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

use App\Enums\TenantMembershipRole;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenantMemberships()->exists();
    }

    public function view(User $user, Product $product): bool
    {
        return $this->membershipFor($user, $product) !== null;
    }

    public function create(User $user, Tenant $tenant): bool
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $tenant->id)
            ->where('role', TenantMembershipRole::Owner)
            ->exists();
    }

    public function update(User $user, Product $product): bool
    {
        return $this->membershipFor($user, $product)?->role === TenantMembershipRole::Owner;
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->update($user, $product);
    }

    private function membershipFor(User $user, Product $product): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $product->tenant_id)
            ->first();
    }
}
```

- [ ] **Step 4: Write the Form Requests**

```php
<?php

namespace App\Http\Requests\Catalog;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
            'price.required' => 'Harga wajib diisi.',
            'price.min' => 'Harga tidak boleh negatif.',
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Catalog;

use App\Enums\ProductStatus;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->tenantId();

        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'category_id' => [
                'nullable',
                'string',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', new Enum(ProductStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama produk wajib diisi.',
        ];
    }
}
```

- [ ] **Step 5: Write the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Catalog\CreateProduct;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateProductRequest;
use App\Http\Requests\Catalog\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->tenant();

        Gate::authorize('viewAny', Product::class);

        $products = Product::query()
            ->where('tenant_id', $tenant->id)
            ->with('category')
            ->latest()
            ->get();

        return Inertia::render('dashboard/products/index', [
            'products' => $products,
            'can' => [
                'create' => Gate::allows('create', [Product::class, $tenant]),
            ],
        ]);
    }

    public function create(TenantContext $tenantContext): Response
    {
        $tenant = $tenantContext->tenant();

        Gate::authorize('create', [Product::class, $tenant]);

        return Inertia::render('dashboard/products/create', [
            'categories' => Category::query()
                ->where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(CreateProductRequest $request, TenantContext $tenantContext, CreateProduct $createProduct): RedirectResponse
    {
        $tenant = $tenantContext->tenant();

        Gate::authorize('create', [Product::class, $tenant]);

        $product = $createProduct->handle(
            tenant: $tenant,
            name: $request->string('name')->toString(),
            categoryId: $request->input('category_id'),
            description: $request->input('description'),
            price: $request->integer('price'),
        );

        return redirect()->route('products.edit', $product);
    }

    public function edit(Product $product, TenantContext $tenantContext): Response
    {
        Gate::authorize('view', $product);

        $product->load(['category', 'images', 'options.values', 'variants.optionValues.option', 'variants.inventory']);

        return Inertia::render('dashboard/products/edit', [
            'product' => $product,
            'categories' => Category::query()
                ->where('tenant_id', $tenantContext->tenant()->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => [
                'update' => Gate::allows('update', $product),
            ],
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $status = $request->enum('status', ProductStatus::class);

        $product->update([
            'name' => $request->string('name')->toString(),
            'category_id' => $request->input('category_id'),
            'description' => $request->input('description'),
            'status' => $status,
            'published_at' => $status === ProductStatus::Active
                ? ($product->published_at ?? now())
                : $product->published_at,
        ]);

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return redirect()->route('products.index');
    }
}
```

- [ ] **Step 6: Add routes**

Modify `routes/dashboard.php` — add the import and, inside the middleware group, after the `categories.*` routes:

```php
use App\Http\Controllers\Dashboard\ProductController;

Route::get('dashboard/products', [ProductController::class, 'index'])->name('products.index');
Route::get('dashboard/products/create', [ProductController::class, 'create'])->name('products.create');
Route::post('dashboard/products', [ProductController::class, 'store'])->name('products.store');
Route::get('dashboard/products/{product}', [ProductController::class, 'edit'])->name('products.edit');
Route::put('dashboard/products/{product}', [ProductController::class, 'update'])->name('products.update');
Route::delete('dashboard/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
```

- [ ] **Step 7: Run the test**

Run: `docker compose exec app php artisan test --filter=ProductManagementTest`
Expected: PASS — 7 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Policies/ProductPolicy.php app/Http/Requests/Catalog/CreateProductRequest.php app/Http/Requests/Catalog/UpdateProductRequest.php app/Http/Controllers/Dashboard/ProductController.php routes/dashboard.php tests/Feature/Catalog/ProductManagementTest.php
git commit -m "feat: add product dashboard CRUD"
```

---

### Task 10: ProductOptionController (add/remove options and their values)

**Files:**
- Create: `app/Http/Requests/Catalog/CreateProductOptionRequest.php`
- Create: `app/Http/Controllers/Dashboard/ProductOptionController.php`
- Modify: `routes/dashboard.php`
- Test: `tests/Feature/Catalog/ProductOptionManagementTest.php`

**Interfaces:**
- Consumes: `ProductPolicy::update` (Task 9, authorizes on the parent `Product`), `ProductOption`/`ProductOptionValue` (Task 4).
- Produces: named routes `product-options.store`, `product-options.destroy`. These option values are what Task 11's variant form references by ID.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=ProductOptionManagementTest`
Expected: FAIL — route `product-options.store` not defined.

- [ ] **Step 3: Write the Form Request**

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'values' => ['required', 'array', 'min:1', 'max:20'],
            'values.*' => ['required', 'string', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama opsi wajib diisi, contoh: Ukuran.',
            'values.required' => 'Tambahkan minimal satu nilai, contoh: S, M, L.',
            'values.max' => 'Maksimal 20 nilai per opsi.',
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateProductOptionRequest;
use App\Models\Product;
use App\Models\ProductOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductOptionController extends Controller
{
    private const MAX_OPTIONS_PER_PRODUCT = 3;

    public function store(CreateProductOptionRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        if ($product->options()->count() >= self::MAX_OPTIONS_PER_PRODUCT) {
            throw new HttpException(422, 'Maksimal '.self::MAX_OPTIONS_PER_PRODUCT.' opsi per produk.');
        }

        $option = ProductOption::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'name' => $request->string('name')->toString(),
            'sort_order' => $product->options()->count(),
        ]);

        foreach (array_values($request->array('values')) as $index => $value) {
            $option->values()->create([
                'value' => $value,
                'sort_order' => $index,
            ]);
        }

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product, ProductOption $option): RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless($option->product_id === $product->id, 404);

        $option->delete();

        return redirect()->route('products.edit', $product);
    }
}
```

- [ ] **Step 5: Add routes**

Modify `routes/dashboard.php` — add the import and, after the `products.*` routes:

```php
use App\Http\Controllers\Dashboard\ProductOptionController;

Route::post('dashboard/products/{product}/options', [ProductOptionController::class, 'store'])->name('product-options.store');
Route::delete('dashboard/products/{product}/options/{option}', [ProductOptionController::class, 'destroy'])->name('product-options.destroy');
```

- [ ] **Step 6: Run the test**

Run: `docker compose exec app php artisan test --filter=ProductOptionManagementTest`
Expected: PASS — 5 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Catalog/CreateProductOptionRequest.php app/Http/Controllers/Dashboard/ProductOptionController.php routes/dashboard.php tests/Feature/Catalog/ProductOptionManagementTest.php
git commit -m "feat: add product option management"
```

---

### Task 11: ProductVariantController (store/update/destroy)

**Files:**
- Create: `app/Http/Requests/Catalog/CreateProductVariantRequest.php`
- Create: `app/Http/Requests/Catalog/UpdateProductVariantRequest.php`
- Create: `app/Http/Controllers/Dashboard/ProductVariantController.php`
- Modify: `routes/dashboard.php`
- Test: `tests/Feature/Catalog/ProductVariantManagementTest.php`

**Interfaces:**
- Consumes: `AddProductVariant` action (Task 7), `ProductOptionValue` (Task 4), `ProductPolicy::update` (Task 9).
- Produces: named routes `product-variants.store`, `product-variants.update`, `product-variants.destroy`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=ProductVariantManagementTest`
Expected: FAIL — route `product-variants.store` not defined.

- [ ] **Step 3: Write the Form Requests**

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
            'sku_suffix' => ['nullable', 'string', 'max:50'],
            'option_value_ids' => ['array', 'max:3'],
            'option_value_ids.*' => ['string', 'exists:product_option_values,id'],
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'integer', 'min:0'],
            'sale_price' => ['nullable', 'integer', 'min:0', 'lt:price'],
            'weight_grams' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Catalog\AddProductVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateProductVariantRequest;
use App\Http\Requests\Catalog\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductVariantController extends Controller
{
    public function store(CreateProductVariantRequest $request, Product $product, AddProductVariant $addProductVariant): RedirectResponse
    {
        Gate::authorize('update', $product);

        $optionValueIds = $request->array('option_value_ids');
        $this->assertOptionValuesBelongToProduct($product, $optionValueIds);

        $addProductVariant->handle(
            product: $product,
            price: $request->integer('price'),
            salePrice: $request->filled('sale_price') ? $request->integer('sale_price') : null,
            weightGrams: $request->filled('weight_grams') ? $request->integer('weight_grams') : null,
            optionValueIds: $optionValueIds,
            skuSuffix: $request->input('sku_suffix'),
        );

        return redirect()->route('products.edit', $product);
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertVariantBelongsToProduct($product, $variant);

        $variant->update([
            'price' => $request->integer('price'),
            'sale_price' => $request->filled('sale_price') ? $request->integer('sale_price') : null,
            'weight_grams' => $request->filled('weight_grams') ? $request->integer('weight_grams') : null,
        ]);

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertVariantBelongsToProduct($product, $variant);

        abort_if($product->variants()->count() <= 1, 422, 'Produk harus memiliki minimal satu varian.');

        $variant->delete();

        return redirect()->route('products.edit', $product);
    }

    /**
     * @param  array<int, string>  $optionValueIds
     */
    private function assertOptionValuesBelongToProduct(Product $product, array $optionValueIds): void
    {
        if ($optionValueIds === []) {
            return;
        }

        $validCount = ProductOptionValue::query()
            ->whereIn('id', $optionValueIds)
            ->whereHas('option', fn ($query) => $query->where('product_id', $product->id))
            ->count();

        if ($validCount !== count($optionValueIds)) {
            throw new HttpException(422, 'Nilai opsi tidak valid untuk produk ini.');
        }
    }

    private function assertVariantBelongsToProduct(Product $product, ProductVariant $variant): void
    {
        abort_unless($variant->product_id === $product->id, 404);
    }
}
```

- [ ] **Step 5: Add routes**

Modify `routes/dashboard.php` — add the import and, after the `product-options.*` routes:

```php
use App\Http\Controllers\Dashboard\ProductVariantController;

Route::post('dashboard/products/{product}/variants', [ProductVariantController::class, 'store'])->name('product-variants.store');
Route::put('dashboard/products/{product}/variants/{variant}', [ProductVariantController::class, 'update'])->name('product-variants.update');
Route::delete('dashboard/products/{product}/variants/{variant}', [ProductVariantController::class, 'destroy'])->name('product-variants.destroy');
```

- [ ] **Step 6: Run the test**

Run: `docker compose exec app php artisan test --filter=ProductVariantManagementTest`
Expected: PASS — 5 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Catalog/CreateProductVariantRequest.php app/Http/Requests/Catalog/UpdateProductVariantRequest.php app/Http/Controllers/Dashboard/ProductVariantController.php routes/dashboard.php tests/Feature/Catalog/ProductVariantManagementTest.php
git commit -m "feat: add product variant management"
```

---

### Task 12: ProductImageController (upload/delete)

**Files:**
- Create: `app/Http/Requests/Catalog/UploadProductImageRequest.php`
- Create: `app/Http/Requests/Catalog/ReorderProductImagesRequest.php`
- Create: `app/Http/Controllers/Dashboard/ProductImageController.php`
- Modify: `routes/dashboard.php`
- Test: `tests/Feature/Catalog/ProductImageManagementTest.php`

**Interfaces:**
- Consumes: `ProductImage` (Task 4), `ProductPolicy::update` (Task 9).
- Produces: named routes `product-images.store`, `product-images.reorder`, `product-images.destroy`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_upload_a_product_image(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $image = $product->images()->sole();
        Storage::disk('s3')->assertExists($image->path);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->create('dokumen.pdf', 100),
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, $product->images()->count());
    }

    public function test_the_seventh_image_is_rejected(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        ProductImage::factory()->count(6)->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $response = $this->actingAs($owner)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_delete_a_product_image(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $image = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'path' => 'products/test.jpg']);
        Storage::disk('s3')->put($image->path, 'fake-contents');

        $response = $this->actingAs($owner)->delete(route('product-images.destroy', [$product, $image]));

        $response->assertRedirect(route('products.edit', $product));
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('s3')->assertMissing($image->path);
    }

    public function test_staff_cannot_upload_or_delete_images(): void
    {
        Storage::fake('s3');
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $image = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $this->actingAs($staff)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->image('produk.jpg'),
        ])->assertForbidden();

        $this->actingAs($staff)->delete(route('product-images.destroy', [$product, $image]))->assertForbidden();
    }

    public function test_owner_can_reorder_images(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $first = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'sort_order' => 1]);

        $response = $this->actingAs($owner)->put(route('product-images.reorder', $product), [
            'image_ids' => [$second->id, $first->id],
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_reorder_rejects_an_image_id_from_another_product(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $ownImage = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $otherProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
        $foreignImage = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $otherProduct->id]);

        $response = $this->actingAs($owner)->put(route('product-images.reorder', $product), [
            'image_ids' => [$foreignImage->id, $ownImage->id],
        ]);

        $response->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=ProductImageManagementTest`
Expected: FAIL — route `product-images.store` not defined.

- [ ] **Step 3: Write the Form Request**

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Pilih gambar untuk diunggah.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus jpg, png, atau webp.',
            'image.max' => 'Ukuran gambar maksimal 4MB.',
        ];
    }
}
```

```php
<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class ReorderProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['string'],
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ReorderProductImagesRequest;
use App\Http\Requests\Catalog\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductImageController extends Controller
{
    private const MAX_IMAGES_PER_PRODUCT = 6;

    public function store(UploadProductImageRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        if ($product->images()->count() >= self::MAX_IMAGES_PER_PRODUCT) {
            throw new HttpException(422, 'Maksimal '.self::MAX_IMAGES_PER_PRODUCT.' gambar per produk.');
        }

        $path = $request->file('image')->store("products/{$product->tenant_id}", 's3');

        ProductImage::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'path' => $path,
            'sort_order' => $product->images()->count(),
        ]);

        return redirect()->route('products.edit', $product);
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $imageIds = $request->array('image_ids');
        $ownedCount = $product->images()->whereIn('id', $imageIds)->count();

        if ($ownedCount !== count($imageIds) || $ownedCount !== $product->images()->count()) {
            throw new HttpException(422, 'Daftar gambar tidak valid untuk produk ini.');
        }

        foreach (array_values($imageIds) as $index => $imageId) {
            ProductImage::query()->whereKey($imageId)->update(['sort_order' => $index]);
        }

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('s3')->delete($image->path);
        $image->delete();

        return redirect()->route('products.edit', $product);
    }
}
```

- [ ] **Step 5: Add routes**

Modify `routes/dashboard.php` — add the import and, after the `product-variants.*` routes:

```php
use App\Http\Controllers\Dashboard\ProductImageController;

Route::post('dashboard/products/{product}/images', [ProductImageController::class, 'store'])->name('product-images.store');
Route::put('dashboard/products/{product}/images/reorder', [ProductImageController::class, 'reorder'])->name('product-images.reorder');
Route::delete('dashboard/products/{product}/images/{image}', [ProductImageController::class, 'destroy'])->name('product-images.destroy');
```

Note the `reorder` route is declared before `{image}` so `/images/reorder` doesn't get captured by the `{image}` wildcard — Laravel matches routes in registration order, so a static segment (`reorder`) must be registered ahead of a dynamic one (`{image}`) at the same position. Since `reorder` uses `PUT` and `destroy` uses `DELETE`, they don't actually collide here, but keep the static-before-dynamic ordering habit for any future GET/PATCH addition at this path.

- [ ] **Step 6: Run the test**

Run: `docker compose exec app php artisan test --filter=ProductImageManagementTest`
Expected: PASS — 7 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Requests/Catalog/UploadProductImageRequest.php app/Http/Requests/Catalog/ReorderProductImagesRequest.php app/Http/Controllers/Dashboard/ProductImageController.php routes/dashboard.php tests/Feature/Catalog/ProductImageManagementTest.php
git commit -m "feat: add product image upload, reorder, and deletion"
```

---

### Task 13: InventoryPolicy + InventoryController (record stock movements)

**Files:**
- Create: `app/Policies/InventoryPolicy.php`
- Create: `app/Http/Requests/Inventory/AdjustInventoryRequest.php`
- Create: `app/Http/Controllers/Dashboard/InventoryController.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/dashboard.php`
- Test: `tests/Feature/Inventory/InventoryAdjustmentHttpTest.php`

**Interfaces:**
- Consumes: `AdjustInventory` action (Task 8), `ProductVariant` (Task 4).
- Produces: named route `inventory-movements.store`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class InventoryAdjustmentHttpTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_restock_a_variant(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 0]);

        $response = $this->actingAs($owner)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Restock->value,
            'quantity_delta' => 50,
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(50, $variant->inventory()->first()->on_hand);
    }

    public function test_staff_can_also_adjust_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 10]);

        $response = $this->actingAs($staff)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Adjustment->value,
            'quantity_delta' => -2,
            'note' => 'Rusak saat pengecekan',
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(8, $variant->inventory()->first()->on_hand);
    }

    public function test_stock_cannot_go_negative(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 3]);

        $response = $this->actingAs($owner)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Adjustment->value,
            'quantity_delta' => -10,
        ]);

        $response->assertSessionHasErrors('quantity_delta');
        $this->assertSame(3, $variant->inventory()->first()->on_hand);
    }

    public function test_a_user_outside_the_tenant_cannot_adjust_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        [, $otherOwner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id]);

        $this->actingAs($otherOwner)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Restock->value,
            'quantity_delta' => 10,
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec app php artisan test --filter=InventoryAdjustmentHttpTest`
Expected: FAIL — route `inventory-movements.store` not defined.

- [ ] **Step 3: Write the policy**

```php
<?php

namespace App\Policies;

use App\Models\ProductVariant;
use App\Models\TenantMembership;
use App\Models\User;

class InventoryPolicy
{
    public function adjust(User $user, ProductVariant $variant): bool
    {
        return $this->membershipFor($user, $variant) !== null;
    }

    private function membershipFor(User $user, ProductVariant $variant): ?TenantMembership
    {
        return $user->tenantMemberships()
            ->where('tenant_id', $variant->tenant_id)
            ->first();
    }
}
```

- [ ] **Step 4: Register the policy explicitly**

`InventoryPolicy` authorizes on `ProductVariant`, which doesn't match Laravel's `{Model}Policy` auto-discovery convention (that would look for `ProductVariantPolicy`), so it needs an explicit `Gate::policy()` registration.

Modify `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\ProductVariant;
use App\Policies\InventoryPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Gate::policy(ProductVariant::class, InventoryPolicy::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
```

- [ ] **Step 5: Write the Form Request**

```php
<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class AdjustInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', new Enum(InventoryMovementType::class)],
            'quantity_delta' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity_delta.required' => 'Jumlah perubahan stok wajib diisi.',
            'quantity_delta.not_in' => 'Jumlah perubahan stok tidak boleh nol.',
        ];
    }
}
```

- [ ] **Step 6: Write the controller**

```php
<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class InventoryController extends Controller
{
    public function store(
        AdjustInventoryRequest $request,
        Product $product,
        ProductVariant $variant,
        AdjustInventory $adjustInventory,
    ): RedirectResponse {
        abort_unless($variant->product_id === $product->id, 404);

        Gate::authorize('adjust', $variant);

        try {
            $adjustInventory->handle(
                variant: $variant,
                type: InventoryMovementType::from($request->string('type')->toString()),
                quantityDelta: $request->integer('quantity_delta'),
                note: $request->input('note'),
                actor: $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['quantity_delta' => $exception->getMessage()]);
        }

        return redirect()->route('products.edit', $product);
    }
}
```

- [ ] **Step 7: Add routes**

Modify `routes/dashboard.php` — add the import and, after the `product-images.*` routes:

```php
use App\Http\Controllers\Dashboard\InventoryController;

Route::post('dashboard/products/{product}/variants/{variant}/inventory-movements', [InventoryController::class, 'store'])->name('inventory-movements.store');
```

- [ ] **Step 8: Run the test**

Run: `docker compose exec app php artisan test --filter=InventoryAdjustmentHttpTest`
Expected: PASS — 4 tests.

- [ ] **Step 9: Commit**

```bash
git add app/Policies/InventoryPolicy.php app/Http/Requests/Inventory/AdjustInventoryRequest.php app/Http/Controllers/Dashboard/InventoryController.php app/Providers/AppServiceProvider.php routes/dashboard.php tests/Feature/Inventory/InventoryAdjustmentHttpTest.php
git commit -m "feat: add dashboard stock adjustment, open to staff"
```

---

### Task 14: Frontend — `dashboard/products/index.tsx` and `dashboard/products/create.tsx`

**Files:**
- Create: `resources/js/pages/dashboard/products/index.tsx`
- Create: `resources/js/pages/dashboard/products/create.tsx`

**Interfaces:**
- Consumes: `products.*` routes (Task 9) via generated `@/actions/App/Http/Controllers/Dashboard/ProductController`; `categories` prop shape from `ProductController::create()` (Task 9).

- [ ] **Step 1: Write `products/index.tsx`**

```tsx
import { Head, Link } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Dashboard/ProductController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Product = {
    id: string;
    name: string;
    status: 'draft' | 'active' | 'archived';
    category: { id: string; name: string } | null;
};

type Props = {
    products: Product[];
    can: {
        create: boolean;
    };
};

const statusLabel: Record<Product['status'], string> = {
    draft: 'Draf',
    active: 'Aktif',
    archived: 'Diarsipkan',
};

export default function ProductsIndex({ products, can }: Props) {
    return (
        <>
            <Head title="Produk" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Produk"
                        description="Kelola katalog produk toko Anda"
                    />

                    {can.create && (
                        <Button asChild>
                            <Link href={ProductController.create().url}>
                                Tambah produk
                            </Link>
                        </Button>
                    )}
                </div>

                {products.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada produk.
                    </p>
                )}

                <ul className="divide-y divide-border rounded-md border">
                    {products.map((product) => (
                        <li key={product.id}>
                            <Link
                                href={ProductController.edit(product.id).url}
                                className="flex items-center justify-between gap-4 p-4 hover:bg-accent"
                            >
                                <div>
                                    <p className="font-medium">
                                        {product.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {product.category?.name ??
                                            'Tanpa kategori'}
                                    </p>
                                </div>
                                <Badge variant="secondary">
                                    {statusLabel[product.status]}
                                </Badge>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
```

- [ ] **Step 2: Write `products/create.tsx`**

```tsx
import { Form, Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Dashboard/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Category = {
    id: string;
    name: string;
};

type Props = {
    categories: Category[];
};

export default function ProductsCreate({ categories }: Props) {
    return (
        <>
            <Head title="Produk baru" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Produk baru"
                    description="Setelah disimpan, Anda dapat menambahkan gambar, opsi, dan varian"
                />

                <Form
                    {...ProductController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama produk</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Kategori</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    defaultValue=""
                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                >
                                    <option value="">Tanpa kategori</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">Harga (Rp)</Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    min={0}
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>

                            <Button type="submit">
                                {processing && <Spinner />}
                                Simpan dan lanjutkan
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
```

- [ ] **Step 3: Type-check, lint, and format**

Run: `docker compose exec app npm run types:check && docker compose exec app npm run lint:check && docker compose exec app npm run format:check`
Expected: PASS. If `ProductController.create().url` / `ProductController.edit(product.id).url` don't match the generated file, adjust to match `resources/js/actions/App/Http/Controllers/Dashboard/ProductController.ts`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/pages/dashboard/products/index.tsx resources/js/pages/dashboard/products/create.tsx
git commit -m "feat: add product list and creation pages"
```

---

### Task 15: Frontend — `dashboard/products/edit.tsx` (images, options, variants, stock) + full verification

**Files:**
- Create: `resources/js/pages/dashboard/products/edit.tsx`

**Interfaces:**
- Consumes: `products.update`, `product-images.store`/`.reorder`/`.destroy`, `product-options.store`/`.destroy`, `product-variants.store`/`.update`/`.destroy`, `inventory-movements.store` routes (Tasks 9–13) via their generated action modules; the `product` prop shape returned by `ProductController::edit()` (Task 9: `category`, `images`, `options.values`, `variants.optionValues.option`, `variants.inventory`).

- [ ] **Step 1: Write the page**

```tsx
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InventoryController from '@/actions/App/Http/Controllers/Dashboard/InventoryController';
import ProductController from '@/actions/App/Http/Controllers/Dashboard/ProductController';
import ProductImageController from '@/actions/App/Http/Controllers/Dashboard/ProductImageController';
import ProductOptionController from '@/actions/App/Http/Controllers/Dashboard/ProductOptionController';
import ProductVariantController from '@/actions/App/Http/Controllers/Dashboard/ProductVariantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type OptionValue = { id: string; value: string };
type Option = { id: string; name: string; values: OptionValue[] };
type Variant = {
    id: string;
    sku: string;
    price: number;
    sale_price: number | null;
    weight_grams: number | null;
    option_values: OptionValue[];
    inventory: { on_hand: number; reserved: number } | null;
};
type ProductImage = { id: string; path: string; sort_order: number };
type Category = { id: string; name: string };

type Product = {
    id: string;
    name: string;
    description: string | null;
    status: 'draft' | 'active' | 'archived';
    category_id: string | null;
    images: ProductImage[];
    options: Option[];
    variants: Variant[];
};

type Props = {
    product: Product;
    categories: Category[];
    can: {
        update: boolean;
    };
};

function AddOptionForm({ productId }: { productId: string }) {
    const [valueCount, setValueCount] = useState(1);

    return (
        <Form
            {...ProductOptionController.store.form(productId)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="max-w-sm space-y-2 rounded-md border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1">
                        <Label htmlFor="option-name">Nama opsi</Label>
                        <Input
                            id="option-name"
                            name="name"
                            placeholder="Contoh: Ukuran"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    {Array.from({ length: valueCount }).map((_, index) => (
                        <Input
                            key={index}
                            name="values[]"
                            placeholder={`Nilai ${index + 1}, contoh: M`}
                            required
                        />
                    ))}
                    <InputError message={errors.values} />

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setValueCount((count) => count + 1)}
                        >
                            Tambah nilai
                        </Button>
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan opsi
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function moveImage(productId: string, images: ProductImage[], index: number, direction: -1 | 1) {
    const target = index + direction;

    if (target < 0 || target >= images.length) {
        return;
    }

    const reordered = [...images];
    [reordered[index], reordered[target]] = [reordered[target], reordered[index]];

    router.put(
        ProductImageController.reorder(productId).url,
        { image_ids: reordered.map((image) => image.id) },
        { preserveScroll: true },
    );
}

export default function ProductsEdit({ product, categories, can }: Props) {
    return (
        <>
            <Head title={product.name} />

            <div className="space-y-10 p-4">
                <Heading
                    title={product.name}
                    description="Kelola detail, gambar, opsi, varian, dan stok produk"
                />

                <section className="max-w-xl space-y-6">
                    <h2 className="text-lg font-semibold">Detail produk</h2>
                    <Form
                        {...ProductController.update.form(product.id)}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing={can.update}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama produk</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        disabled={!can.update}
                                        defaultValue={product.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="category_id">
                                        Kategori
                                    </Label>
                                    <select
                                        id="category_id"
                                        name="category_id"
                                        disabled={!can.update}
                                        defaultValue={
                                            product.category_id ?? ''
                                        }
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="">
                                            Tanpa kategori
                                        </option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.category_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Deskripsi
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        disabled={!can.update}
                                        defaultValue={
                                            product.description ?? ''
                                        }
                                        className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                    />
                                    <InputError
                                        message={errors.description}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        disabled={!can.update}
                                        defaultValue={product.status}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="draft">Draf</option>
                                        <option value="active">Aktif</option>
                                        <option value="archived">
                                            Diarsipkan
                                        </option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>

                                {can.update && (
                                    <Button type="submit">
                                        {processing && <Spinner />}
                                        Simpan
                                    </Button>
                                )}
                            </>
                        )}
                    </Form>
                </section>

                <section className="max-w-xl space-y-4">
                    <h2 className="text-lg font-semibold">Gambar</h2>

                    <div className="flex flex-wrap gap-4">
                        {product.images.map((image, index) => (
                            <div key={image.id} className="space-y-2">
                                <img
                                    src={`/storage/${image.path}`}
                                    alt=""
                                    className="size-24 rounded-md border object-cover"
                                />
                                {can.update && (
                                    <>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveImage(
                                                        product.id,
                                                        product.images,
                                                        index,
                                                        -1,
                                                    )
                                                }
                                            >
                                                ↑
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    index ===
                                                    product.images.length - 1
                                                }
                                                onClick={() =>
                                                    moveImage(
                                                        product.id,
                                                        product.images,
                                                        index,
                                                        1,
                                                    )
                                                }
                                            >
                                                ↓
                                            </Button>
                                        </div>
                                        <Form
                                            {...ProductImageController.destroy.form(
                                                [product.id, image.id],
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Hapus
                                                </Button>
                                            )}
                                        </Form>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>

                    {can.update && product.images.length < 6 && (
                        <Form
                            {...ProductImageController.store.form(product.id)}
                            options={{ preserveScroll: true }}
                            encType="multipart/form-data"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Input
                                        type="file"
                                        name="image"
                                        accept="image/jpeg,image/png,image/webp"
                                        required
                                    />
                                    <InputError message={errors.image} />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        className="mt-2"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Unggah gambar
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </section>

                <section className="max-w-xl space-y-4">
                    <h2 className="text-lg font-semibold">Opsi</h2>

                    {product.options.map((option) => (
                        <div
                            key={option.id}
                            className="flex items-center justify-between rounded-md border p-4"
                        >
                            <div>
                                <p className="font-medium">{option.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {option.values
                                        .map((value) => value.value)
                                        .join(', ')}
                                </p>
                            </div>
                            {can.update && (
                                <Form
                                    {...ProductOptionController.destroy.form([
                                        product.id,
                                        option.id,
                                    ])}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Hapus
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    ))}

                    {can.update && product.options.length < 3 && (
                        <AddOptionForm productId={product.id} />
                    )}
                </section>

                <section className="max-w-2xl space-y-4">
                    <h2 className="text-lg font-semibold">
                        Varian &amp; stok
                    </h2>

                    <ul className="divide-y divide-border rounded-md border">
                        {product.variants.map((variant) => (
                            <li key={variant.id} className="space-y-4 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {variant.sku}
                                        </p>
                                        {variant.option_values.length > 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                {variant.option_values
                                                    .map(
                                                        (value) =>
                                                            value.value,
                                                    )
                                                    .join(' / ')}
                                            </p>
                                        )}
                                        <p className="text-sm text-muted-foreground">
                                            Stok:{' '}
                                            {variant.inventory?.on_hand ?? 0}
                                        </p>
                                    </div>

                                    {can.update &&
                                        product.variants.length > 1 && (
                                            <Form
                                                {...ProductVariantController.destroy.form(
                                                    [product.id, variant.id],
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Hapus varian
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                </div>

                                {can.update && (
                                    <Form
                                        {...ProductVariantController.update.form(
                                            [product.id, variant.id],
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="grid grid-cols-3 items-end gap-2"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`price-${variant.id}`}
                                                    >
                                                        Harga
                                                    </Label>
                                                    <Input
                                                        id={`price-${variant.id}`}
                                                        name="price"
                                                        type="number"
                                                        min={0}
                                                        required
                                                        defaultValue={
                                                            variant.price
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.price
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`sale-${variant.id}`}
                                                    >
                                                        Harga diskon
                                                    </Label>
                                                    <Input
                                                        id={`sale-${variant.id}`}
                                                        name="sale_price"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={
                                                            variant.sale_price ??
                                                            ''
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.sale_price
                                                        }
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    {processing && (
                                                        <Spinner />
                                                    )}
                                                    Simpan
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                )}

                                <Form
                                    {...InventoryController.store.form([
                                        product.id,
                                        variant.id,
                                    ])}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="grid grid-cols-3 items-end gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`type-${variant.id}`}
                                                >
                                                    Jenis
                                                </Label>
                                                <select
                                                    id={`type-${variant.id}`}
                                                    name="type"
                                                    defaultValue="restock"
                                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                                >
                                                    <option value="restock">
                                                        Tambah stok
                                                    </option>
                                                    <option value="adjustment">
                                                        Sesuaikan stok
                                                    </option>
                                                </select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`delta-${variant.id}`}
                                                >
                                                    Jumlah (+/-)
                                                </Label>
                                                <Input
                                                    id={`delta-${variant.id}`}
                                                    name="quantity_delta"
                                                    type="number"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.quantity_delta
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Catat
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}
```

- [ ] **Step 2: Type-check, lint, and format**

Run: `docker compose exec app npm run types:check && docker compose exec app npm run lint:check && docker compose exec app npm run format:check`
Expected: PASS. If any `Controller.method.form([a, b])` call doesn't match the generated multi-param signature in its `resources/js/actions/App/Http/Controllers/Dashboard/*.ts` file, open that file and adjust the call to whatever shape it actually exports (object vs. positional array).

- [ ] **Step 3: Commit the page**

```bash
git add resources/js/pages/dashboard/products/edit.tsx
git commit -m "feat: add product edit page with images, options, variants, and stock"
```

- [ ] **Step 4: Run the full backend quality suite**

Run: `docker compose exec app php artisan test`
Expected: all tests pass, including every test from Tasks 1–13 (71 pre-existing Phase 1 tests + the new catalog/inventory tests).

Run: `docker compose exec app ./vendor/bin/pint --test`
Expected: PASS. If not, run `docker compose exec app ./vendor/bin/pint` to auto-fix, then re-run tests.

Run: `docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M`
Expected: `[OK] No errors`.

- [ ] **Step 5: Run the full frontend quality suite**

Run: `docker compose exec app npm run lint:check && docker compose exec app npm run format:check && docker compose exec app npm run types:check && docker compose exec app npm run test`
Expected: all PASS.

Run: `docker compose exec app npm run build && docker compose exec app npm run build:ssr`
Expected: both succeed.

- [ ] **Step 6: Manually verify in the browser**

The stack is already running (`docker compose ps` should show `nginx`, `app`, `postgres`, `redis` healthy). Using an existing owner account (or `php artisan tinker` / the onboarding flow to create one against `http://usaharumahan.localhost:8180` or the mapped port from `compose.yaml`):

1. Visit `/dashboard/categories`, create a category, edit it, delete it.
2. Visit `/dashboard/products`, create a product with a price, confirm redirect to its edit page.
3. On the edit page: upload two images and use the ↑/↓ buttons to reorder them, add an option with values, add a variant selecting those values, edit a variant's price, restock a variant, confirm the stock number updates, delete the non-default variant.
4. Log in as a staff member of the same tenant and confirm: catalog write buttons are hidden/disabled, but stock can still be adjusted.

Fix anything that doesn't work before considering the task done — this is a real UI, not just passing tests.

- [ ] **Step 7: Update `docs/PROGRESS.md`**

Add a "Phase 2 — Dashboard Catalog & Inventory" section documenting what was implemented, mirroring the structure of the existing "Phase 0 — Foundation" section (Implemented / Tests added / Quality results / Known limitations / Next phase — public storefront listing/detail pages, per the spec's deferred scope).

- [ ] **Step 8: Commit**

```bash
git add docs/PROGRESS.md
git commit -m "docs: record Phase 2 dashboard catalog/inventory progress"
```
