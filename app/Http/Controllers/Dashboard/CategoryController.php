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

    public function update(UpdateCategoryRequest $request, Category $category, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $category);
        $this->assertCategoryBelongsToActiveTenant($category, $tenantContext);

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

    public function destroy(Category $category, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('delete', $category);
        $this->assertCategoryBelongsToActiveTenant($category, $tenantContext);

        $category->delete();

        return redirect()->route('categories.index');
    }

    /**
     * A user can be an Owner of more than one tenant at once (see
     * `ResolveTenantForDashboard`). The Policy only checks generic
     * membership, so a route-bound model belonging to a tenant the user
     * owns but does NOT currently have selected must be rejected here —
     * otherwise a category from tenant A could be mutated while tenant B
     * is the active dashboard context.
     */
    private function assertCategoryBelongsToActiveTenant(Category $category, TenantContext $tenantContext): void
    {
        abort_unless($category->tenant_id === $tenantContext->tenantId(), 404);
    }
}
