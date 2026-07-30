import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InventoryController from '@/actions/App/Http/Controllers/Dashboard/InventoryController';
import ProductController from '@/actions/App/Http/Controllers/Dashboard/ProductController';
import ProductImageController from '@/actions/App/Http/Controllers/Dashboard/ProductImageController';
import ProductOptionController from '@/actions/App/Http/Controllers/Dashboard/ProductOptionController';
import ProductVariantController from '@/actions/App/Http/Controllers/Dashboard/ProductVariantController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type OptionValue = { id: string; value: string };
type Option = { id: string; name: string; values: OptionValue[] };
type Variant = {
    id: string;
    sku: string;
    price: number;
    sale_price: number | null;
    weight_grams: number | null;
    option_values: OptionValue[];
    inventory: { on_hand: number; reserved: number } | null;
};
type ProductImage = { id: string; path: string; sort_order: number };
type Category = { id: string; name: string };

type Product = {
    id: string;
    name: string;
    description: string | null;
    status: 'draft' | 'active' | 'archived';
    category_id: string | null;
    images: ProductImage[];
    options: Option[];
    variants: Variant[];
};

type Props = {
    product: Product;
    categories: Category[];
    can: {
        update: boolean;
    };
};

function AddOptionForm({ productId }: { productId: string }) {
    const [valueCount, setValueCount] = useState(1);

    return (
        <Form
            {...ProductOptionController.store.form(productId)}
            options={{ preserveScroll: true }}
            resetOnSuccess
            className="max-w-sm space-y-2 rounded-md border p-4"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-1">
                        <Label htmlFor="option-name">Nama opsi</Label>
                        <Input
                            id="option-name"
                            name="name"
                            placeholder="Contoh: Ukuran"
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    {Array.from({ length: valueCount }).map((_, index) => (
                        <Input
                            key={index}
                            name="values[]"
                            placeholder={`Nilai ${index + 1}, contoh: M`}
                            required
                        />
                    ))}
                    <InputError message={errors.values} />

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setValueCount((count) => count + 1)}
                        >
                            Tambah nilai
                        </Button>
                        <Button type="submit" size="sm" disabled={processing}>
                            {processing && <Spinner />}
                            Simpan opsi
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

function moveImage(
    productId: string,
    images: ProductImage[],
    index: number,
    direction: -1 | 1,
) {
    const target = index + direction;

    if (target < 0 || target >= images.length) {
        return;
    }

    const reordered = [...images];
    [reordered[index], reordered[target]] = [
        reordered[target],
        reordered[index],
    ];

    router.put(
        ProductImageController.reorder(productId).url,
        { image_ids: reordered.map((image) => image.id) },
        { preserveScroll: true },
    );
}

export default function ProductsEdit({ product, categories, can }: Props) {
    return (
        <>
            <Head title={product.name} />

            <div className="space-y-10 p-4">
                <Heading
                    title={product.name}
                    description="Kelola detail, gambar, opsi, varian, dan stok produk"
                />

                <section className="max-w-xl space-y-6">
                    <h2 className="text-lg font-semibold">Detail produk</h2>
                    <Form
                        {...ProductController.update.form(product.id)}
                        options={{ preserveScroll: true }}
                        disableWhileProcessing={can.update}
                        className="space-y-6"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nama produk</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        required
                                        disabled={!can.update}
                                        defaultValue={product.name}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="category_id">
                                        Kategori
                                    </Label>
                                    <select
                                        id="category_id"
                                        name="category_id"
                                        disabled={!can.update}
                                        defaultValue={product.category_id ?? ''}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="">Tanpa kategori</option>
                                        {categories.map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="description">
                                        Deskripsi
                                    </Label>
                                    <textarea
                                        id="description"
                                        name="description"
                                        disabled={!can.update}
                                        defaultValue={product.description ?? ''}
                                        className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                    />
                                    <InputError message={errors.description} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        disabled={!can.update}
                                        defaultValue={product.status}
                                        className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                    >
                                        <option value="draft">Draf</option>
                                        <option value="active">Aktif</option>
                                        <option value="archived">
                                            Diarsipkan
                                        </option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>

                                {can.update && (
                                    <Button type="submit">
                                        {processing && <Spinner />}
                                        Simpan
                                    </Button>
                                )}
                            </>
                        )}
                    </Form>
                </section>

                <section className="max-w-xl space-y-4">
                    <h2 className="text-lg font-semibold">Gambar</h2>

                    <div className="flex flex-wrap gap-4">
                        {product.images.map((image, index) => (
                            <div key={image.id} className="space-y-2">
                                <img
                                    src={`/storage/${image.path}`}
                                    alt=""
                                    className="size-24 rounded-md border object-cover"
                                />
                                {can.update && (
                                    <>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveImage(
                                                        product.id,
                                                        product.images,
                                                        index,
                                                        -1,
                                                    )
                                                }
                                            >
                                                ↑
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    index ===
                                                    product.images.length - 1
                                                }
                                                onClick={() =>
                                                    moveImage(
                                                        product.id,
                                                        product.images,
                                                        index,
                                                        1,
                                                    )
                                                }
                                            >
                                                ↓
                                            </Button>
                                        </div>
                                        <Form
                                            {...ProductImageController.destroy.form(
                                                [product.id, image.id],
                                            )}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="destructive"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Hapus
                                                </Button>
                                            )}
                                        </Form>
                                    </>
                                )}
                            </div>
                        ))}
                    </div>

                    {can.update && product.images.length < 6 && (
                        <Form
                            {...ProductImageController.store.form(product.id)}
                            options={{ preserveScroll: true }}
                            encType="multipart/form-data"
                            resetOnSuccess
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Input
                                        type="file"
                                        name="image"
                                        accept="image/jpeg,image/png,image/webp"
                                        required
                                    />
                                    <InputError message={errors.image} />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        className="mt-2"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Unggah gambar
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}
                </section>

                <section className="max-w-xl space-y-4">
                    <h2 className="text-lg font-semibold">Opsi</h2>

                    {product.options.map((option) => (
                        <div
                            key={option.id}
                            className="flex items-center justify-between rounded-md border p-4"
                        >
                            <div>
                                <p className="font-medium">{option.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {option.values
                                        .map((value) => value.value)
                                        .join(', ')}
                                </p>
                            </div>
                            {can.update && (
                                <Form
                                    {...ProductOptionController.destroy.form([
                                        product.id,
                                        option.id,
                                    ])}
                                >
                                    {({ processing }) => (
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Hapus
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    ))}

                    {can.update && product.options.length < 3 && (
                        <AddOptionForm productId={product.id} />
                    )}
                </section>

                <section className="max-w-2xl space-y-4">
                    <h2 className="text-lg font-semibold">Varian &amp; stok</h2>

                    <ul className="divide-y divide-border rounded-md border">
                        {product.variants.map((variant) => (
                            <li key={variant.id} className="space-y-4 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium">
                                            {variant.sku}
                                        </p>
                                        {variant.option_values.length > 0 && (
                                            <p className="text-sm text-muted-foreground">
                                                {variant.option_values
                                                    .map((value) => value.value)
                                                    .join(' / ')}
                                            </p>
                                        )}
                                        <p className="text-sm text-muted-foreground">
                                            Stok:{' '}
                                            {variant.inventory?.on_hand ?? 0}
                                        </p>
                                    </div>

                                    {can.update &&
                                        product.variants.length > 1 && (
                                            <Form
                                                {...ProductVariantController.destroy.form(
                                                    [product.id, variant.id],
                                                )}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        size="sm"
                                                        disabled={processing}
                                                    >
                                                        Hapus varian
                                                    </Button>
                                                )}
                                            </Form>
                                        )}
                                </div>

                                {can.update && (
                                    <Form
                                        {...ProductVariantController.update.form(
                                            [product.id, variant.id],
                                        )}
                                        options={{ preserveScroll: true }}
                                        className="grid grid-cols-3 items-end gap-2"
                                    >
                                        {({ processing, errors }) => (
                                            <>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`price-${variant.id}`}
                                                    >
                                                        Harga
                                                    </Label>
                                                    <Input
                                                        id={`price-${variant.id}`}
                                                        name="price"
                                                        type="number"
                                                        min={0}
                                                        required
                                                        defaultValue={
                                                            variant.price
                                                        }
                                                    />
                                                    <InputError
                                                        message={errors.price}
                                                    />
                                                </div>
                                                <div className="grid gap-1">
                                                    <Label
                                                        htmlFor={`sale-${variant.id}`}
                                                    >
                                                        Harga diskon
                                                    </Label>
                                                    <Input
                                                        id={`sale-${variant.id}`}
                                                        name="sale_price"
                                                        type="number"
                                                        min={0}
                                                        defaultValue={
                                                            variant.sale_price ??
                                                            ''
                                                        }
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.sale_price
                                                        }
                                                    />
                                                </div>
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    {processing && <Spinner />}
                                                    Simpan
                                                </Button>
                                            </>
                                        )}
                                    </Form>
                                )}

                                <Form
                                    {...InventoryController.store.form([
                                        product.id,
                                        variant.id,
                                    ])}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="grid grid-cols-3 items-end gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`type-${variant.id}`}
                                                >
                                                    Jenis
                                                </Label>
                                                <select
                                                    id={`type-${variant.id}`}
                                                    name="type"
                                                    defaultValue="restock"
                                                    className="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                                                >
                                                    <option value="restock">
                                                        Tambah stok
                                                    </option>
                                                    <option value="adjustment">
                                                        Sesuaikan stok
                                                    </option>
                                                </select>
                                            </div>
                                            <div className="grid gap-1">
                                                <Label
                                                    htmlFor={`delta-${variant.id}`}
                                                >
                                                    Jumlah (+/-)
                                                </Label>
                                                <Input
                                                    id={`delta-${variant.id}`}
                                                    name="quantity_delta"
                                                    type="number"
                                                    required
                                                />
                                                <InputError
                                                    message={
                                                        errors.quantity_delta
                                                    }
                                                />
                                            </div>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                {processing && <Spinner />}
                                                Catat
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}
