import { Form, Head } from '@inertiajs/react';
import StoreController from '@/actions/App/Http/Controllers/Dashboard/StoreController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Store = {
    name: string;
    description: string | null;
    whatsapp_number: string | null;
    email: string | null;
    address: string | null;
    province: string | null;
    city: string | null;
    district: string | null;
    postal_code: string | null;
    is_open: boolean;
};

type Props = {
    store: Store;
    can: {
        update: boolean;
    };
};

export default function StoreEdit({ store, can }: Props) {
    return (
        <>
            <Head title="Profil Toko" />

            <div className="space-y-6">
                <Heading
                    title="Profil toko"
                    description="Informasi ini akan tampil di halaman depan toko Anda"
                />

                <Form
                    {...StoreController.update.form()}
                    options={{ preserveScroll: true }}
                    disableWhileProcessing={can.update}
                    className="max-w-xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama toko</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    disabled={!can.update}
                                    defaultValue={store.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Deskripsi</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    disabled={!can.update}
                                    defaultValue={store.description ?? ''}
                                    className="min-h-24 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs disabled:opacity-50"
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp_number">
                                    Nomor WhatsApp
                                </Label>
                                <Input
                                    id="whatsapp_number"
                                    name="whatsapp_number"
                                    disabled={!can.update}
                                    placeholder="+62812xxxxxxx"
                                    defaultValue={store.whatsapp_number ?? ''}
                                />
                                <InputError message={errors.whatsapp_number} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Email toko</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    disabled={!can.update}
                                    defaultValue={store.email ?? ''}
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">Alamat</Label>
                                <textarea
                                    id="address"
                                    name="address"
                                    disabled={!can.update}
                                    defaultValue={store.address ?? ''}
                                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs disabled:opacity-50"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="city">Kota/Kabupaten</Label>
                                    <Input
                                        id="city"
                                        name="city"
                                        disabled={!can.update}
                                        defaultValue={store.city ?? ''}
                                    />
                                    <InputError message={errors.city} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="province">Provinsi</Label>
                                    <Input
                                        id="province"
                                        name="province"
                                        disabled={!can.update}
                                        defaultValue={store.province ?? ''}
                                    />
                                    <InputError message={errors.province} />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_open"
                                    name="is_open"
                                    disabled={!can.update}
                                    defaultChecked={store.is_open}
                                />
                                <Label
                                    htmlFor="is_open"
                                    className="font-normal"
                                >
                                    Toko sedang buka menerima pesanan
                                </Label>
                            </div>

                            {can.update && (
                                <Button type="submit">
                                    {processing && <Spinner />}
                                    Simpan
                                </Button>
                            )}

                            {!can.update && (
                                <p className="text-sm text-muted-foreground">
                                    Hanya pemilik toko yang dapat mengubah
                                    profil toko.
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
