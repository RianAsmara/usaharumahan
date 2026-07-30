<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class InventoryAdjustmentHttpTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_restock_a_variant(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 0]);

        $response = $this->actingAs($owner)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Restock->value,
            'quantity_delta' => 50,
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(50, $variant->inventory()->first()->on_hand);
    }

    public function test_staff_can_also_adjust_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 10]);

        $response = $this->actingAs($staff)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Adjustment->value,
            'quantity_delta' => -2,
            'note' => 'Rusak saat pengecekan',
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(8, $variant->inventory()->first()->on_hand);
    }

    public function test_stock_cannot_go_negative(): void
    {
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'on_hand' => 3]);

        $response = $this->actingAs($owner)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Adjustment->value,
            'quantity_delta' => -10,
        ]);

        $response->assertSessionHasErrors('quantity_delta');
        $this->assertSame(3, $variant->inventory()->first()->on_hand);
    }

    public function test_a_user_outside_the_tenant_cannot_adjust_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        [, $otherOwner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        Inventory::factory()->create(['tenant_id' => $tenant->id, 'product_variant_id' => $variant->id]);

        $this->actingAs($otherOwner)->post(route('inventory-movements.store', [$product, $variant]), [
            'type' => InventoryMovementType::Restock->value,
            'quantity_delta' => 10,
        ])->assertForbidden();
    }
}
