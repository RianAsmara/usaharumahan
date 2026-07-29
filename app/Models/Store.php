<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string|null $whatsapp_number
 * @property string|null $email
 * @property string|null $address
 * @property string|null $province
 * @property string|null $city
 * @property string|null $district
 * @property string|null $postal_code
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $logo_path
 * @property string|null $banner_path
 * @property string|null $primary_color
 * @property array<string, string>|null $social_links
 * @property array<string, mixed>|null $opening_hours
 * @property bool $is_open
 * @property bool $is_published
 */
#[Fillable([
    'tenant_id', 'name', 'description', 'whatsapp_number', 'email', 'address',
    'province', 'city', 'district', 'postal_code', 'latitude', 'longitude',
    'logo_path', 'banner_path', 'primary_color', 'social_links',
    'opening_hours', 'is_open', 'is_published',
])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'social_links' => 'array',
            'opening_hours' => 'array',
            'is_open' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
