<?php

namespace App\Models;

use App\Enums\DomainVerificationStatus;
use App\Enums\StoreDomainType;
use Database\Factories\StoreDomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $hostname
 * @property StoreDomainType $type
 * @property bool $is_primary
 * @property DomainVerificationStatus $verification_status
 * @property string|null $verification_token
 * @property Carbon|null $verified_at
 * @property string|null $ssl_status
 * @property Carbon|null $last_checked_at
 */
#[Fillable([
    'tenant_id', 'hostname', 'type', 'is_primary', 'verification_status',
    'verification_token', 'verified_at',
])]
class StoreDomain extends Model
{
    /** @use HasFactory<StoreDomainFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'type' => StoreDomainType::class,
            'is_primary' => 'boolean',
            'verification_status' => DomainVerificationStatus::class,
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === DomainVerificationStatus::Verified;
    }
}
