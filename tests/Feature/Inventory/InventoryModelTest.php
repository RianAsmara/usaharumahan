<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class InventoryModelTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_variant_has_one_inventory_row(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 5]);

        $this->assertSame(5, $variant->inventory->on_hand);
        $this->assertSame(5, $variant->inventory->available());
    }

    public function test_available_stock_subtracts_reserved_from_on_hand(): void
    {
        $inventory = Inventory::factory()->make(['on_hand' => 10, 'reserved' => 3]);

        $this->assertSame(7, $inventory->available());
    }

    public function test_a_movement_records_the_resulting_on_hand_snapshot(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id]);

        $movement = InventoryMovement::factory()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'quantity_delta' => 10,
            'resulting_on_hand' => 10,
        ]);

        $this->assertTrue($movement->variant->is($variant));
        $this->assertNull($movement->actor);
    }
}
