<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ReorderProductImagesRequest;
use App\Http\Requests\Catalog\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductImageController extends Controller
{
    private const MAX_IMAGES_PER_PRODUCT = 6;

    public function store(UploadProductImageRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        if ($product->images()->count() >= self::MAX_IMAGES_PER_PRODUCT) {
            throw new HttpException(422, 'Maksimal '.self::MAX_IMAGES_PER_PRODUCT.' gambar per produk.');
        }

        $path = $request->file('image')->store("products/{$product->tenant_id}", 's3');

        ProductImage::query()->create([
            'tenant_id' => $product->tenant_id,
            'product_id' => $product->id,
            'path' => $path,
            'sort_order' => $product->images()->count(),
        ]);

        return redirect()->route('products.edit', $product);
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);

        $imageIds = $request->array('image_ids');
        $ownedCount = $product->images()->whereIn('id', $imageIds)->count();

        if ($ownedCount !== count($imageIds) || $ownedCount !== $product->images()->count()) {
            throw new HttpException(422, 'Daftar gambar tidak valid untuk produk ini.');
        }

        foreach (array_values($imageIds) as $index => $imageId) {
            ProductImage::query()->whereKey($imageId)->update(['sort_order' => $index]);
        }

        return redirect()->route('products.edit', $product);
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        Gate::authorize('update', $product);
        abort_unless($image->product_id === $product->id, 404);

        Storage::disk('s3')->delete($image->path);
        $image->delete();

        return redirect()->route('products.edit', $product);
    }
}
