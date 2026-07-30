<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'type' => InventoryMovementType::Restock,
            'quantity_delta' => 10,
            'resulting_on_hand' => 10,
        ];
    }
}
