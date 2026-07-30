<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateProductOptionRequest;
use App\Models\Product;
use App\Models\ProductOption;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductOptionController extends Controller
{
    private const MAX_OPTIONS_PER_PRODUCT = 3;

    public function store(CreateProductOptionRequest $request, Product $product, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);

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
                'tenant_id' => $product->tenant_id,
                'value' => $value,
                'sort_order' => $index,
            ]);
        }

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product, ProductOption $option, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);
        abort_unless($option->product_id === $product->id, 404);

        $option->delete();

        return redirect()->route('products.edit', $product);
    }

    /**
     * A user can be an Owner of more than one tenant at once, so a
     * route-bound $product belonging to a tenant the user owns but does
     * NOT currently have selected must be rejected — see
     * `ProductController::assertProductBelongsToActiveTenant()` for the
     * full rationale.
     */
    private function assertProductBelongsToActiveTenant(Product $product, TenantContext $tenantContext): void
    {
        abort_unless($product->tenant_id === $tenantContext->tenantId(), 404);
    }
}
