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
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);

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

    public function update(UpdateProductRequest $request, Product $product, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);

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

    public function destroy(Product $product, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('delete', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);

        $product->delete();

        return redirect()->route('products.index');
    }

    /**
     * A user can be an Owner of more than one tenant at once (see
     * `ResolveTenantForDashboard`). The Policy only checks generic
     * membership, so a route-bound product belonging to a tenant the user
     * owns but does NOT currently have selected must be rejected here —
     * otherwise a request scoped to the active tenant (e.g. its category
     * list in `UpdateProductRequest`) could silently act on a different
     * tenant's product.
     */
    private function assertProductBelongsToActiveTenant(Product $product, TenantContext $tenantContext): void
    {
        abort_unless($product->tenant_id === $tenantContext->tenantId(), 404);
    }
}
