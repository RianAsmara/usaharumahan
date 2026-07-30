import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import CategoryController from '@/actions/App/Http/Controllers/Dashboard/CategoryController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Category = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    is_active: boolean;
};

type Props = {
    categories: Category[];
    can: {
        create: boolean;
    };
};

export default function CategoriesIndex({ categories, can }: Props) {
    const [editing, setEditing] = useState<Category | null>(null);
    const [creating, setCreating] = useState(false);

    return (
        <>
            <Head title="Kategori" />

            <div className="space-y-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Kategori"
                        description="Kelompokkan produk Anda agar mudah ditemukan pelanggan"
                    />

                    {can.create && (
                        <Dialog open={creating} onOpenChange={setCreating}>
                            <DialogTrigger asChild>
                                <Button>Tambah kategori</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <DialogHeader>
                                    <DialogTitle>Kategori baru</DialogTitle>
                                </DialogHeader>
                                <Form
                                    {...CategoryController.store.form()}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setCreating(false)}
                                    className="space-y-4"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="name">
                                                    Nama kategori
                                                </Label>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    required
                                                    autoFocus
                                                />
                                                <InputError
                                                    message={errors.name}
                                                />
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="description">
                                                    Deskripsi
                                                </Label>
                                                <textarea
                                                    id="description"
                                                    name="description"
                                                    className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                                />
                                                <InputError
                                                    message={errors.description}
                                                />
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id="is_active"
                                                    name="is_active"
                                                    defaultChecked
                                                />
                                                <Label
                                                    htmlFor="is_active"
                                                    className="font-normal"
                                                >
                                                    Aktif
                                                </Label>
                                            </div>
                                            <DialogFooter>
                                                <Button type="submit">
                                                    {processing && <Spinner />}
                                                    Simpan
                                                </Button>
                                            </DialogFooter>
                                        </>
                                    )}
                                </Form>
                            </DialogContent>
                        </Dialog>
                    )}
                </div>

                {categories.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada kategori.
                    </p>
                )}

                <ul className="divide-y divide-border rounded-md border">
                    {categories.map((category) => (
                        <li
                            key={category.id}
                            className="flex items-center justify-between gap-4 p-4"
                        >
                            <div>
                                <p className="font-medium">{category.name}</p>
                                {category.description && (
                                    <p className="text-sm text-muted-foreground">
                                        {category.description}
                                    </p>
                                )}
                                {!category.is_active && (
                                    <p className="text-xs text-muted-foreground">
                                        Nonaktif
                                    </p>
                                )}
                            </div>

                            {can.create && (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditing(category)}
                                    >
                                        Ubah
                                    </Button>
                                    <Form
                                        {...CategoryController.destroy.form(
                                            category.id,
                                        )}
                                        onBefore={() =>
                                            confirm(
                                                `Hapus kategori "${category.name}"?`,
                                            )
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="destructive"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Hapus
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Ubah kategori</DialogTitle>
                    </DialogHeader>
                    {editing && (
                        <Form
                            {...CategoryController.update.form(editing.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-name">
                                            Nama kategori
                                        </Label>
                                        <Input
                                            id="edit-name"
                                            name="name"
                                            required
                                            defaultValue={editing.name}
                                        />
                                        <InputError message={errors.name} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="edit-description">
                                            Deskripsi
                                        </Label>
                                        <textarea
                                            id="edit-description"
                                            name="description"
                                            defaultValue={
                                                editing.description ?? ''
                                            }
                                            className="min-h-20 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                        />
                                        <InputError
                                            message={errors.description}
                                        />
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="edit-is_active"
                                            name="is_active"
                                            defaultChecked={editing.is_active}
                                        />
                                        <Label
                                            htmlFor="edit-is_active"
                                            className="font-normal"
                                        >
                                            Aktif
                                        </Label>
                                    </div>
                                    <DialogFooter>
                                        <Button type="submit">
                                            {processing && <Spinner />}
                                            Simpan
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
