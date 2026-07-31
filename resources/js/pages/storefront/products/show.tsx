import { Head } from '@inertiajs/react';

type Props = {
    store: { name: string };
    product: { name: string };
};

export default function ProductShow({ store, product }: Props) {
    return (
        <>
            <Head title={`${product.name} — ${store.name}`} />
            <div className="mx-auto max-w-2xl px-4 py-10">
                <p>{product.name}</p>
            </div>
        </>
    );
}
