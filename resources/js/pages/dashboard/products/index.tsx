import { Head, Link } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Dashboard/ProductController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Product = {
    id: string;
    name: string;
    status: 'draft' | 'active' | 'archived';
    category: { id: string; name: string } | null;
};

type Props = {
    products: Product[];
    can: {
        create: boolean;
    };
};

const statusLabel: Record<Product['status'], string> = {
    draft: 'Draf',
    active: 'Aktif',
    archived: 'Diarsipkan',
};

export default function ProductsIndex({ products, can }: Props) {
    return (
        <>
            <Head title="Produk" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Produk"
                        description="Kelola katalog produk toko Anda"
                    />

                    {can.create && (
                        <Button asChild>
                            <Link href={ProductController.create().url}>
                                Tambah produk
                            </Link>
                        </Button>
                    )}
                </div>

                {products.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada produk.
                    </p>
                )}

                <ul className="divide-y divide-border rounded-md border">
                    {products.map((product) => (
                        <li key={product.id}>
                            <Link
                                href={ProductController.edit(product.id).url}
                                className="flex items-center justify-between gap-4 p-4 hover:bg-accent"
                            >
                                <div>
                                    <p className="font-medium">
                                        {product.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {product.category?.name ??
                                            'Tanpa kategori'}
                                    </p>
                                </div>
                                <Badge variant="secondary">
                                    {statusLabel[product.status]}
                                </Badge>
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}
