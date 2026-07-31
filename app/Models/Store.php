<?php

namespace App\Models;

use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    protected $appends = ['logo_url', 'banner_url'];

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

    /**
     * @return Attribute<string|null, never>
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->logo_path === null ? null : Storage::disk('s3')->url($this->logo_path),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function bannerUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->banner_path === null ? null : Storage::disk('s3')->url($this->banner_path),
        );
    }

    /**
     * @return array{name: string, description: ?string, whatsappNumber: ?string, isOpen: bool, primaryColor: ?string, logoUrl: ?string, bannerUrl: ?string}
     */
    public function toStorefrontArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'whatsappNumber' => $this->whatsapp_number,
            'isOpen' => $this->is_open,
            'primaryColor' => $this->primary_color,
            'logoUrl' => $this->logo_url,
            'bannerUrl' => $this->banner_url,
        ];
    }
}
