import { Form, Head, Link } from '@inertiajs/react';
import TenantController from '@/actions/App/Http/Controllers/PlatformAdmin/TenantController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

type Tenant = {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'suspended';
    owner: { name: string; email: string };
};

type Props = {
    tenants: {
        data: Tenant[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    search: string;
};

export default function PlatformAdminTenantsIndex({ tenants, search }: Props) {
    return (
        <>
            <Head title="Tenant" />

            <div className="space-y-6 p-6">
                <Heading
                    title="Tenant"
                    description="Semua toko yang terdaftar di platform"
                />

                <Form
                    {...TenantController.index.form()}
                    method="get"
                    className="max-w-sm"
                >
                    <Input
                        type="search"
                        name="search"
                        placeholder="Cari nama atau subdomain..."
                        defaultValue={search}
                    />
                </Form>

                <div className="overflow-x-auto rounded-lg border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="p-3 font-medium">Nama</th>
                                <th className="p-3 font-medium">Subdomain</th>
                                <th className="p-3 font-medium">Pemilik</th>
                                <th className="p-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {tenants.data.map((tenant) => (
                                <tr key={tenant.id} className="border-t">
                                    <td className="p-3">
                                        <Link
                                            href={TenantController.show(
                                                tenant.id,
                                            )}
                                            className="font-medium hover:underline"
                                        >
                                            {tenant.name}
                                        </Link>
                                    </td>
                                    <td className="p-3 text-muted-foreground">
                                        {tenant.slug}
                                    </td>
                                    <td className="p-3">
                                        {tenant.owner.name}
                                        <div className="text-xs text-muted-foreground">
                                            {tenant.owner.email}
                                        </div>
                                    </td>
                                    <td className="p-3">
                                        <Badge
                                            variant={
                                                tenant.status === 'active'
                                                    ? 'default'
                                                    : 'destructive'
                                            }
                                        >
                                            {tenant.status}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                            {tenants.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
                                        className="p-6 text-center text-muted-foreground"
                                    >
                                        Belum ada tenant.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
