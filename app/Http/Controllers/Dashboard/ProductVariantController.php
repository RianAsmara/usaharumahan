<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Catalog\AddProductVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CreateProductVariantRequest;
use App\Http\Requests\Catalog\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductVariantController extends Controller
{
    public function store(CreateProductVariantRequest $request, Product $product, AddProductVariant $addProductVariant, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);

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

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);
        $this->assertVariantBelongsToProduct($product, $variant);

        $variant->update([
            'price' => $request->integer('price'),
            'sale_price' => $request->filled('sale_price') ? $request->integer('sale_price') : null,
            'weight_grams' => $request->filled('weight_grams') ? $request->integer('weight_grams') : null,
        ]);

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product, ProductVariant $variant, TenantContext $tenantContext): RedirectResponse
    {
        Gate::authorize('update', $product);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);
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
