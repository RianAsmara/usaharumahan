<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class InventoryController extends Controller
{
    public function store(
        AdjustInventoryRequest $request,
        Product $product,
        ProductVariant $variant,
        AdjustInventory $adjustInventory,
        TenantContext $tenantContext,
    ): RedirectResponse {
        abort_unless($variant->product_id === $product->id, 404);

        Gate::authorize('adjust', $variant);
        $this->assertProductBelongsToActiveTenant($product, $tenantContext);

        try {
            $adjustInventory->handle(
                variant: $variant,
                type: InventoryMovementType::from($request->string('type')->toString()),
                quantityDelta: $request->integer('quantity_delta'),
                note: $request->input('note'),
                actor: $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['quantity_delta' => $exception->getMessage()]);
        }

        return redirect()->route('products.edit', $product);
    }

    /**
     * A user can be an Owner of more than one tenant at once, so a
     * route-bound $product belonging to a tenant the user owns but does
     * NOT currently have selected must be rejected — see
     * `ProductController::assertProductBelongsToActiveTenant()` for the
     * full rationale.
     */
    private function assertProductBelongsToActiveTenant(Product $product, TenantContext $tenantContext): void
    {
        abort_unless($product->tenant_id === $tenantContext->tenantId(), 404);
    }
}
