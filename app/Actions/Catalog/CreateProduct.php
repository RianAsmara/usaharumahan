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
