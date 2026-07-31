import { Head, Link } from '@inertiajs/react';
import { StorefrontLayout } from '@/components/storefront/storefront-layout';

export default function StorefrontNotFound() {
    return (
        <StorefrontLayout primaryColor={null}>
            <Head title="Produk tidak ditemukan" />

            <div className="mx-auto flex min-h-screen max-w-md flex-col items-center justify-center gap-3 px-4 text-center">
                <p className="font-serif text-3xl font-semibold">Produk tidak ditemukan</p>
                <p className="text-[var(--storefront-ink)]/70">
                    Produk yang Anda cari sudah tidak tersedia atau alamatnya salah.
                </p>
                <Link
                    href="/"
                    className="mt-2 inline-flex items-center gap-2 rounded-full bg-[var(--tenant-accent)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--tenant-accent-hover)]"
                >
                    ← Kembali ke etalase
                </Link>
            </div>
        </StorefrontLayout>
    );
}
