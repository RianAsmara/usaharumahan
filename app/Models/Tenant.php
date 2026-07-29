<?php

namespace App\Models;

use App\Enums\TenantStatus;
use App\Enums\TenantSubscriptionStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 * @property int $owner_user_id
 * @property TenantSubscriptionStatus $subscription_status
 * @property Carbon|null $trial_starts_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $suspended_at
 */
#[Fillable(['name', 'slug', 'owner_user_id', 'status', 'suspended_at', 'trial_starts_at', 'trial_ends_at'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'subscription_status' => TenantSubscriptionStatus::class,
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasMany<TenantMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    /**
     * @return HasOne<Store, $this>
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    /**
     * @return HasMany<StoreDomain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }
}
