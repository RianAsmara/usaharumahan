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
