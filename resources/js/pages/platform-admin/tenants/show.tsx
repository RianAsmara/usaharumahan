import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';

type Tenant = {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended';
    subscription_status: string;
    created_at: string;
    owner: { name: string; email: string };
    store: { name: string; is_published: boolean } | null;
    domains: {
        hostname: string;
        type: string;
        verification_status: string;
        is_primary: boolean;
    }[];
};

type Props = {
    tenant: Tenant;
};

export default function PlatformAdminTenantShow({ tenant }: Props) {
    return (
        <>
            <Head title={tenant.name} />

            <div className="max-w-2xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading title={tenant.name} description={tenant.slug} />
                    <Badge
                        variant={
                            tenant.status === 'active'
                                ? 'default'
                                : 'destructive'
                        }
                    >
                        {tenant.status}
                    </Badge>
                </div>

                <dl className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Pemilik</dt>
                        <dd>
                            {tenant.owner.name} ({tenant.owner.email})
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Langganan</dt>
                        <dd>{tenant.subscription_status}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Terdaftar</dt>
                        <dd>{tenant.created_at}</dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Toko</dt>
                        <dd>
                            {tenant.store
                                ? `${tenant.store.name} (${tenant.store.is_published ? 'dipublikasikan' : 'draf'})`
                                : 'Belum dikonfigurasi'}
                        </dd>
                    </div>
                </dl>

                <div>
                    <h3 className="mb-2 text-sm font-medium">Domain</h3>
                    <ul className="space-y-2">
                        {tenant.domains.map((domain) => (
                            <li
                                key={domain.hostname}
                                className="flex items-center justify-between rounded-md border p-2 text-sm"
                            >
                                <span>{domain.hostname}</span>
                                <span className="text-muted-foreground">
                                    {domain.type} &middot;{' '}
                                    {domain.verification_status}
                                    {domain.is_primary && ' · utama'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </>
    );
}
