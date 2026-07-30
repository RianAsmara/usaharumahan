<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\AddProductVariant;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class AddProductVariantActionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_it_adds_a_variant_with_option_values_and_zero_stock(): void
    {
        [$tenant] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Kaos Polos']);
        $option = ProductOption::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'name' => 'Ukuran']);
        $valueM = ProductOptionValue::factory()->create(['product_option_id' => $option->id, 'value' => 'M']);

        $variant = app(AddProductVariant::class)->handle(
            product: $product,
            price: 50000,
            salePrice: null,
            weightGrams: 200,
            optionValueIds: [$valueM->id],
            skuSuffix: 'M',
        );

        $this->assertSame(50000, $variant->price);
        $this->assertTrue($variant->optionValues->first()->is($valueM));
        $this->assertSame(0, $variant->inventory->on_hand);
        $this->assertStringContainsString('KAOS-POLOS-M', $variant->sku);
    }
}
