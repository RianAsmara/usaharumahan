<?php

namespace Database\Factories;

use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductOptionValue>
 */
class ProductOptionValueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_option_id' => ProductOption::factory(),
            'value' => fake()->randomElement(['S', 'M', 'L']),
            'sort_order' => 0,
        ];
    }
}
