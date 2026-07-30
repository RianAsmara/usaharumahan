<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_variant_id
 * @property InventoryMovementType $type
 * @property int $quantity_delta
 * @property int $resulting_on_hand
 * @property string|null $note
 * @property int|null $actor_user_id
 */
#[Fillable(['tenant_id', 'product_variant_id', 'type', 'quantity_delta', 'resulting_on_hand', 'note', 'actor_user_id'])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory, HasUlids;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
