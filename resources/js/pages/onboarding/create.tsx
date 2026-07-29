import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import OnboardingController from '@/actions/App/Http/Controllers/Dashboard/OnboardingController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    rootDomain: string;
};

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

export default function OnboardingCreate({ rootDomain }: Props) {
    const [subdomain, setSubdomain] = useState('');

    return (
        <>
            <Head title="Buat Toko" />
            <Form
                {...OnboardingController.store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nama toko</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    name="name"
                                    placeholder="Dapur Ibu"
                                    onChange={(event) =>
                                        setSubdomain(
                                            slugify(event.target.value),
                                        )
                                    }
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="subdomain">
                                    Subdomain toko
                                </Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="subdomain"
                                        type="text"
                                        required
                                        name="subdomain"
                                        placeholder="dapur-ibu"
                                        value={subdomain}
                                        onChange={(event) =>
                                            setSubdomain(
                                                slugify(event.target.value),
                                            )
                                        }
                                    />
                                    <span className="text-sm whitespace-nowrap text-muted-foreground">
                                        .{rootDomain}
                                    </span>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Pelanggan akan mengunjungi toko Anda di{' '}
                                    {subdomain || 'nama-toko'}.{rootDomain}
                                </p>
                                <InputError message={errors.subdomain} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                data-test="create-tenant-button"
                            >
                                {processing && <Spinner />}
                                Buat toko
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

OnboardingCreate.layout = {
    title: 'Buat toko Anda',
    description:
        'Beri nama toko Anda dan pilih subdomain untuk mulai berjualan',
};
