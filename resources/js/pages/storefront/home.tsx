import { Head } from '@inertiajs/react';
import { ProductCard, type StorefrontProduct } from '@/components/storefront/product-card';
import { StorefrontLayout } from '@/components/storefront/storefront-layout';

type Store = {
    name: string;
    description: string | null;
    whatsappNumber: string | null;
    isOpen: boolean;
    primaryColor: string | null;
    logoUrl: string | null;
    bannerUrl: string | null;
};

type Props = {
    store: Store;
    products: StorefrontProduct[];
};

export default function StorefrontHome({ store, products }: Props) {
    const whatsappHref = store.whatsappNumber ? `https://wa.me/${store.whatsappNumber.replace(/[^0-9]/g, '')}` : null;

    return (
        <StorefrontLayout primaryColor={store.primaryColor}>
            <Head title={store.name} />

            <header className="relative">
                <div
                    className="aspect-[16/9] w-full sm:aspect-[21/9]"
                    style={
                        store.bannerUrl
                            ? { backgroundImage: `url(${store.bannerUrl})`, backgroundSize: 'cover', backgroundPosition: 'center' }
                            : { backgroundImage: 'linear-gradient(135deg, var(--tenant-tint), var(--storefront-paper))' }
                    }
                >
                    {!store.bannerUrl && <div className="storefront-banner-grain h-full w-full" />}
                </div>

                <div className="mx-auto -mt-10 max-w-3xl px-4">
                    {store.logoUrl ? (
                        <img
                            src={store.logoUrl}
                            alt={store.name}
                            className="h-20 w-20 rounded-full border-4 border-[var(--storefront-paper)] object-cover"
                        />
                    ) : (
                        <div className="flex h-20 w-20 items-center justify-center rounded-full border-4 border-[var(--storefront-paper)] bg-[var(--tenant-accent)] font-serif text-2xl text-white">
                            {store.name.charAt(0).toUpperCase()}
                        </div>
                    )}
                </div>
            </header>

            <div className="mx-auto max-w-3xl space-y-6 px-4 pt-4 pb-16">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <h1 className="font-serif text-2xl font-semibold">{store.name}</h1>
                    <span
                        className={`rounded-full px-3 py-1 text-xs font-medium ${
                            store.isOpen
                                ? 'bg-[var(--storefront-status-open)]/15 text-[var(--storefront-status-open)]'
                                : 'bg-[var(--storefront-status-closed)]/15 text-[var(--storefront-status-closed)]'
                        }`}
                    >
                        {store.isOpen ? 'Buka' : 'Tutup'}
                    </span>
                </div>

                {store.description && <p className="text-[var(--storefront-ink)]/80">{store.description}</p>}

                {whatsappHref && (
                    <a
                        href={whatsappHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex w-fit items-center gap-2 rounded-full bg-[var(--tenant-accent)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--tenant-accent-hover)]"
                    >
                        Hubungi via WhatsApp
                    </a>
                )}

                {products.length === 0 ? (
                    <div className="flex flex-col items-center gap-2 py-16 text-center text-[var(--storefront-ink)]/60">
                        <p className="font-serif text-lg">Belum ada produk dipajang</p>
                        <p className="text-sm">Pantau terus, produk akan segera hadir.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-4">
                        {products.map((product) => (
                            <ProductCard key={product.id} product={product} />
                        ))}
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
