import { Form, Head } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Dashboard/ProductController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Category = {
    id: string;
    name: string;
};

type Props = {
    categories: Category[];
};

export default function ProductsCreate({ categories }: Props) {
    return (
        <>
            <Head title="Produk baru" />

            <div className="space-y-6 p-4">
                <Heading
                    title="Produk baru"
                    description="Setelah disimpan, Anda dapat menambahkan gambar, opsi, dan varian"
                />

                <Form
                    {...ProductController.store.form()}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama produk</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Kategori</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    defaultValue=""
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
                                <Label htmlFor="description">Deskripsi</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="price">Harga (Rp)</Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    min={0}
                                    required
                                />
                                <InputError message={errors.price} />
                            </div>

                            <Button type="submit">
                                {processing && <Spinner />}
                                Simpan dan lanjutkan
                            </Button>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
