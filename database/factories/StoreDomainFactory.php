<?php

namespace Database\Factories;

use App\Enums\DomainVerificationStatus;
use App\Enums\StoreDomainType;
use App\Models\StoreDomain;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StoreDomain>
 */
class StoreDomainFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'hostname' => Str::slug(fake()->unique()->company()).'.'.config('tenancy.root_domain'),
            'type' => StoreDomainType::Subdomain,
            'is_primary' => true,
            'verification_status' => DomainVerificationStatus::Verified,
            'verified_at' => now(),
        ];
    }

    public function custom(): static
    {
        return $this->state(fn (array $attributes) => [
            'hostname' => fake()->unique()->domainName(),
            'type' => StoreDomainType::Custom,
            'is_primary' => false,
            'verification_status' => DomainVerificationStatus::Pending,
            'verified_at' => null,
        ]);
    }
}
