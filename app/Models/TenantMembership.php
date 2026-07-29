<?php

namespace App\Models;

use App\Enums\TenantMembershipRole;
use Database\Factories\TenantMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property int $user_id
 * @property TenantMembershipRole $role
 */
#[Fillable(['tenant_id', 'user_id', 'role'])]
class TenantMembership extends Model
{
    /** @use HasFactory<TenantMembershipFactory> */
    use HasFactory, HasUlids;

    protected function casts(): array
    {
        return [
            'role' => TenantMembershipRole::class,
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === TenantMembershipRole::Owner;
    }
}
