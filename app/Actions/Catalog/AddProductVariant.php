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
