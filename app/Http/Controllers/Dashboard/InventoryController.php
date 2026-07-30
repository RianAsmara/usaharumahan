<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Inventory\AdjustInventory;
use App\Enums\InventoryMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\Product;
use App\Models\ProductVariant;
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
    ): RedirectResponse {
        abort_unless($variant->product_id === $product->id, 404);

        Gate::authorize('adjust', $variant);

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
}
