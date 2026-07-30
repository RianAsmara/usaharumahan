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
                'tenant_id' => $product->tenant_id,
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
