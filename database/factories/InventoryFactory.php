<?php

namespace Database\Factories;

use App\Models\Inventory;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inventory>
 */
class InventoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'on_hand' => 0,
            'reserved' => 0,
        ];
    }
}
