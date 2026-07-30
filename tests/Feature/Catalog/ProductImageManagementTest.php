<?php

namespace Tests\Feature\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

class ProductImageManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_upload_a_product_image(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $image = $product->images()->sole();
        Storage::disk('s3')->assertExists($image->path);
    }

    public function test_a_non_image_file_is_rejected(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($owner)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->create('dokumen.pdf', 100),
        ]);

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, $product->images()->count());
    }

    public function test_the_seventh_image_is_rejected(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        ProductImage::factory()->count(6)->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $response = $this->actingAs($owner)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->image('produk.jpg'),
        ]);

        $response->assertStatus(422);
    }

    public function test_owner_can_delete_a_product_image(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $image = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'path' => 'products/test.jpg']);
        Storage::disk('s3')->put($image->path, 'fake-contents');

        $response = $this->actingAs($owner)->delete(route('product-images.destroy', [$product, $image]));

        $response->assertRedirect(route('products.edit', $product));
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
        Storage::disk('s3')->assertMissing($image->path);
    }

    public function test_staff_cannot_upload_or_delete_images(): void
    {
        Storage::fake('s3');
        [$tenant] = $this->createTenantWithOwner();
        $staff = $this->addStaffToTenant($tenant);
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $image = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);

        $this->actingAs($staff)->post(route('product-images.store', $product), [
            'image' => UploadedFile::fake()->image('produk.jpg'),
        ])->assertForbidden();

        $this->actingAs($staff)->delete(route('product-images.destroy', [$product, $image]))->assertForbidden();
    }

    public function test_owner_can_reorder_images(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $first = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'sort_order' => 1]);

        $response = $this->actingAs($owner)->put(route('product-images.reorder', $product), [
            'image_ids' => [$second->id, $first->id],
        ]);

        $response->assertRedirect(route('products.edit', $product));
        $this->assertSame(0, $second->fresh()->sort_order);
        $this->assertSame(1, $first->fresh()->sort_order);
    }

    public function test_reorder_rejects_an_image_id_from_another_product(): void
    {
        Storage::fake('s3');
        [$tenant, $owner] = $this->createTenantWithOwner();
        $product = Product::factory()->create(['tenant_id' => $tenant->id]);
        $ownImage = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $product->id]);
        $otherProduct = Product::factory()->create(['tenant_id' => $tenant->id]);
        $foreignImage = ProductImage::factory()->create(['tenant_id' => $tenant->id, 'product_id' => $otherProduct->id]);

        $response = $this->actingAs($owner)->put(route('product-images.reorder', $product), [
            'image_ids' => [$foreignImage->id, $ownImage->id],
        ]);

        $response->assertStatus(422);
    }
}
