import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { StorefrontLayout } from '@/components/storefront/storefront-layout';
import { formatRupiah } from '@/lib/currency';
import { findMatchingVariant, isValueAvailable, type Variant } from '@/lib/variant-resolution';

type Store = {
    name: string;
    primaryColor: string | null;
};

type OptionValue = { id: string; value: string };
type ProductOption = { id: string; name: string; values: OptionValue[] };
type ProductImage = { id: string; url: string };

type ProductDetail = {
    name: string;
    description: string | null;
    images: ProductImage[];
    options: ProductOption[];
    variants: Variant[];
};

type Props = {
    store: Store;
    product: ProductDetail;
};

export default function ProductShow({ store, product }: Props) {
    const [selected, setSelected] = useState<Record<string, string>>({});
    const [activeImage, setActiveImage] = useState(0);

    const optionIds = product.options.map((option) => option.id);
    const variant = useMemo(
        () => findMatchingVariant(product.variants, selected, optionIds),
        [product.variants, selected, optionIds],
    );

    const cheapestVariant = useMemo(
        () =>
            product.variants.reduce((cheapest, candidate) => {
                const cheapestEffective = cheapest.salePrice ?? cheapest.price;
                const candidateEffective = candidate.salePrice ?? candidate.price;

                return candidateEffective < cheapestEffective ? candidate : cheapest;
            }, product.variants[0]),
        [product.variants],
    );

    const displayed = variant ?? cheapestVariant;
    const price = displayed.salePrice ?? displayed.price;
    const originalPrice = displayed.salePrice ? displayed.price : null;
    const inStock = displayed.stock > 0;

    return (
        <StorefrontLayout primaryColor={store.primaryColor}>
            <Head title={product.name} />

            <div className="mx-auto max-w-4xl px-4 py-6">
                <Link href="/" className="text-sm text-[var(--storefront-ink)]/60 hover:text-[var(--tenant-accent)]">
                    ← Kembali ke etalase
                </Link>

                <div className="mt-4 grid gap-8 md:grid-cols-2">
                    <div className="md:sticky md:top-6 md:self-start">
                        <div className="aspect-square overflow-hidden rounded-xl bg-[var(--storefront-paper-muted)]">
                            {product.images[activeImage] && (
                                <img
                                    src={product.images[activeImage].url}
                                    alt={product.name}
                                    className="h-full w-full object-cover"
                                />
                            )}
                        </div>
                        {product.images.length > 1 && (
                            <div className="mt-3 flex gap-2 overflow-x-auto md:flex-col">
                                {product.images.map((image, index) => (
                                    <button
                                        key={image.id}
                                        type="button"
                                        onClick={() => setActiveImage(index)}
                                        className={`h-16 w-16 shrink-0 overflow-hidden rounded-lg border-2 ${
                                            index === activeImage ? 'border-[var(--tenant-accent)]' : 'border-transparent'
                                        }`}
                                    >
                                        <img src={image.url} alt="" className="h-full w-full object-cover" />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="space-y-5">
                        <h1 className="font-serif text-2xl font-semibold">{product.name}</h1>

                        <div className="flex items-baseline gap-2">
                            <p className="text-xl font-semibold text-[var(--tenant-accent)]">{formatRupiah(price)}</p>
                            {originalPrice && (
                                <p className="text-sm text-[var(--storefront-ink)]/40 line-through">
                                    {formatRupiah(originalPrice)}
                                </p>
                            )}
                        </div>

                        <p className="flex items-center gap-2 text-sm">
                            <span
                                className={`h-2 w-2 rounded-full ${
                                    inStock ? 'bg-[var(--storefront-status-open)]' : 'bg-[var(--storefront-status-warning)]'
                                }`}
                            />
                            {inStock ? 'Stok tersedia' : 'Stok habis'}
                        </p>

                        {product.options.map((option) => (
                            <div key={option.id} className="space-y-2">
                                <p className="text-xs font-medium tracking-wide text-[var(--storefront-ink)]/60 uppercase">
                                    {option.name}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {option.values.map((value) => {
                                        const isSelected = selected[option.id] === value.id;
                                        const available = isValueAvailable(product.variants, option.id, value.id, selected);

                                        return (
                                            <button
                                                key={value.id}
                                                type="button"
                                                disabled={!available}
                                                onClick={() => setSelected((prev) => ({ ...prev, [option.id]: value.id }))}
                                                className={`rounded-full border px-4 py-1.5 text-sm transition ${
                                                    isSelected
                                                        ? 'border-[var(--tenant-accent)] bg-[var(--tenant-accent)] text-white'
                                                        : 'border-[var(--storefront-ink)]/20'
                                                } ${!available ? 'cursor-not-allowed opacity-40' : ''}`}
                                            >
                                                {value.value}
                                                {!available && <span className="ml-1 text-xs">(habis)</span>}
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}

                        {product.description && (
                            <p className="whitespace-pre-line text-[var(--storefront-ink)]/80">{product.description}</p>
                        )}
                    </div>
                </div>
            </div>
        </StorefrontLayout>
    );
}
