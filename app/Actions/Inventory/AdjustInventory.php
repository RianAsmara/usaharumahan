<?php

namespace App\Actions\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a stock movement and updates on_hand, row-locking the variant's
 * inventory row for the duration of the transaction so two adjustments can
 * never read-then-write a stale on_hand — see docs/DATABASE.md §8. True
 * concurrent-request locking is exercised in Phase 4's checkout reservation
 * tests (docs/TESTING.md §3); this action's own tests prove sequential
 * correctness and that on_hand never goes negative.
 */
class AdjustInventory
{
    public function handle(
        ProductVariant $variant,
        InventoryMovementType $type,
        int $quantityDelta,
        ?string $note,
        User $actor,
    ): InventoryMovement {
        return DB::transaction(function () use ($variant, $type, $quantityDelta, $note, $actor) {
            /** @var Inventory $inventory */
            $inventory = Inventory::query()
                ->whereKey($variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $resultingOnHand = $inventory->on_hand + $quantityDelta;

            if ($resultingOnHand < 0) {
                throw new RuntimeException('Stok tidak boleh menjadi negatif.');
            }

            $inventory->update(['on_hand' => $resultingOnHand]);

            return InventoryMovement::query()->create([
                'tenant_id' => $variant->tenant_id,
                'product_variant_id' => $variant->id,
                'type' => $type,
                'quantity_delta' => $quantityDelta,
                'resulting_on_hand' => $resultingOnHand,
                'note' => $note,
                'actor_user_id' => $actor->id,
            ]);
        });
    }
}
