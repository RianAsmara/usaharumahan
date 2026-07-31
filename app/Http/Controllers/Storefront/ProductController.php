<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\ProductVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function show(string $slug, TenantContext $tenantContext, Request $request): Response
    {
        $tenant = $tenantContext->tenant();

        $product = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('slug', $slug)
            ->published()
            ->with(['images', 'options.values', 'variants.optionValues', 'variants.inventory'])
            ->first();

        if ($product === null) {
            return Inertia::render('storefront/not-found')
                ->toResponse($request)
                ->setStatusCode(404);
        }

        return Inertia::render('storefront/products/show', [
            'store' => $tenant->store->toStorefrontArray(),
            'product' => [
                'name' => $product->name,
                'description' => $product->description,
                'images' => $product->images->map(fn (ProductImage $image): array => [
                    'id' => $image->id,
                    'url' => $image->url,
                ])->all(),
                'options' => $product->options->map(fn (ProductOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'values' => $option->values->map(fn (ProductOptionValue $value): array => [
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
        ])->toResponse($request);
    }
}
