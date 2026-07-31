import { Link } from '@inertiajs/react';
import { formatRupiah } from '@/lib/currency';

export type StorefrontProduct = {
    id: string;
    slug: string;
    name: string;
    imageUrl: string | null;
    price: { amount: number; isFrom: boolean };
    inStock: boolean;
};

type Props = {
    product: StorefrontProduct;
};

export function ProductCard({ product }: Props) {
    return (
        <Link href={`/produk/${product.slug}`} className="group flex flex-col gap-2">
            <div className="relative aspect-[4/5] overflow-hidden rounded-xl bg-[var(--storefront-paper-muted)] shadow-[0_6px_16px_-10px_oklch(0.22_0.02_60/0.4)]">
                {product.imageUrl ? (
                    <img
                        src={product.imageUrl}
                        alt={product.name}
                        className={`h-full w-full object-cover transition group-hover:scale-105 ${
                            product.inStock ? '' : 'opacity-70 saturate-50'
                        }`}
                    />
                ) : (
                    <div className="flex h-full w-full items-center justify-center text-sm text-[var(--storefront-ink)]/50">
                        Tanpa foto
                    </div>
                )}
                {!product.inStock && (
                    <span className="absolute bottom-2 left-2 rounded-full bg-[var(--storefront-status-warning)] px-2 py-0.5 text-xs font-medium text-white">
                        Stok habis
                    </span>
                )}
            </div>
            <div className="space-y-0.5">
                <p className="line-clamp-2 text-sm font-medium">{product.name}</p>
                {product.price.isFrom && <p className="text-xs text-[var(--storefront-ink)]/60">Mulai dari</p>}
                <p className="font-semibold text-[var(--tenant-accent)]">{formatRupiah(product.price.amount)}</p>
            </div>
        </Link>
    );
}
