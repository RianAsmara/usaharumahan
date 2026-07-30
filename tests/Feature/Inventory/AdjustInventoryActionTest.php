<?php

namespace Tests\Feature\Inventory;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AdjustInventoryActionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_restock_increases_on_hand_and_records_a_movement(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 5]);

        $movement = app(AdjustInventory::class)->handle(
            variant: $variant,
            type: InventoryMovementType::Restock,
            quantityDelta: 20,
            note: 'Kiriman baru',
            actor: $owner,
        );

        $this->assertSame(25, $movement->resulting_on_hand);
        $this->assertSame(25, $variant->inventory()->first()->on_hand);
        $this->assertSame($owner->id, $movement->actor_user_id);
    }

    public function test_sequential_adjustments_accumulate_correctly_from_fresh_state(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 0]);

        app(AdjustInventory::class)->handle($variant, InventoryMovementType::Restock, 10, null, $owner);
        app(AdjustInventory::class)->handle($variant, InventoryMovementType::Adjustment, -3, 'Rusak', $owner);
        $last = app(AdjustInventory::class)->handle($variant, InventoryMovementType::Restock, 5, null, $owner);

        $this->assertSame(12, $last->resulting_on_hand);
        $this->assertSame(12, $variant->inventory()->first()->on_hand);
        $this->assertSame(3, InventoryMovement::query()->where('product_variant_id', $variant->id)->count());
    }

    public function test_it_refuses_to_let_stock_go_negative(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 2]);

        $this->expectException(RuntimeException::class);

        app(AdjustInventory::class)->handle($variant, InventoryMovementType::Adjustment, -5, null, $owner);
    }
}
