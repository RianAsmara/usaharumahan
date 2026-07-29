<?php

namespace Database\Factories;

use App\Models\Store;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->company(),
            'whatsapp_number' => '+62'.fake()->numerify('8##########'),
            'is_open' => true,
            'is_published' => false,
        ];
    }
}
