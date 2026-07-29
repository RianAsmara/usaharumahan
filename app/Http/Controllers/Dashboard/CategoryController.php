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
