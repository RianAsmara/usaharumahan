import { Head } from '@inertiajs/react';

type Props = {
    store: {
        name: string;
        description: string | null;
        whatsappNumber: string | null;
        isOpen: boolean;
        primaryColor: string | null;
    };
};

export default function StorefrontHome({ store }: Props) {
    const whatsappHref = store.whatsappNumber
        ? `https://wa.me/${store.whatsappNumber.replace(/[^0-9]/g, '')}`
        : null;

    return (
        <>
            <Head title={store.name} />
            <div className="mx-auto flex min-h-screen max-w-2xl flex-col gap-6 px-4 py-10">
                <header className="flex items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold">{store.name}</h1>
                    <span
                        className={`rounded-full px-3 py-1 text-xs font-medium ${
                            store.isOpen
                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100'
                                : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300'
                        }`}
                    >
                        {store.isOpen ? 'Buka' : 'Tutup'}
                    </span>
                </header>

                {store.description && (
                    <p className="text-muted-foreground">{store.description}</p>
                )}

                <p className="text-sm text-muted-foreground">
                    Katalog produk akan segera hadir di sini.
                </p>

                {whatsappHref && (
                    <a
                        href={whatsappHref}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex w-fit items-center gap-2 rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                    >
                        Hubungi via WhatsApp
                    </a>
                )}
            </div>
        </>
    );
}
