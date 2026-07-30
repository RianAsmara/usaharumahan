<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $product_variant_id
 * @property string $tenant_id
 * @property int $on_hand
 * @property int $reserved
 */
#[Fillable(['product_variant_id', 'tenant_id', 'on_hand', 'reserved'])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $primaryKey = 'product_variant_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function available(): int
    {
        return $this->on_hand - $this->reserved;
    }
}
