<?php

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string $path
 * @property int $sort_order
 * @property-read string $url
 */
#[Fillable(['tenant_id', 'product_id', 'path', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory, HasUlids;

    /**
     * The image is stored on the `s3` disk (MinIO locally) — never the
     * local `public` disk — so the frontend must never construct storage
     * paths itself; it always reads this computed URL instead.
     *
     * @var list<string>
     */
    protected $appends = ['url'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): string => Storage::disk('s3')->url($this->path),
        );
    }
}
