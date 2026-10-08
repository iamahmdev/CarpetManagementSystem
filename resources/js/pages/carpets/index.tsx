import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type CarpetRow = {
    id: number;
    sku: string;
    name: string;
    design: string;
    weave: string;
    size: string;
    color: string;
    in_stock_count: number;
    price: number;
};

type Props = {
    carpets: CarpetRow[];
    canManage: boolean;
};

export default function CarpetsIndex({ carpets, canManage }: Props) {
    const [search, setSearch] = useState('');
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm({
        name: '',
        design: '',
        size: '',
        color: '',
        material: '',
        price: '',
    });
    const filteredCarpets = useMemo(() => {
        const query = search.toLowerCase();

        return carpets.filter((carpet) =>
            `${carpet.name}${carpet.design}${carpet.sku}${carpet.color}`
                .toLowerCase()
                .includes(query),
        );
    }, [carpets, search]);

    function setOpen(open: boolean) {
        setDialogOpen(open);

        if (open) {
            form.reset();
            form.clearErrors();
        }
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            name: data.name.trim(),
            design: data.design.trim(),
            size: data.size.trim(),
            color: data.color.trim(),
            material: data.material.trim(),
            price: data.price.trim(),
        }));
        form.post('/carpets', {
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        });
    }

    return (
        <>
            <Head title="Carpets" />
            <main className="carpets-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Carpets</h1>
                        <p className="sub">
                            The catalog of varieties. Each physical carpet is
                            tracked separately under Pieces.
                        </p>
                    </div>
                    {canManage && (
                        <div className="head-actions">
                            <Button
                                type="button"
                                className="demo-primary-button"
                                onClick={() => setOpen(true)}
                            >
                                <Plus />
                                Add carpet
                            </Button>
                        </div>
                    )}
                </header>

                <section className="carpets-card" aria-label="Carpet catalog">
                    <Input
                        className="carpets-search"
                        type="search"
                        placeholder="Search design, color, or SKU"
                        aria-label="Search carpets"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                    <div className="carpets-table-wrap">
                        <table className="carpets-table">
                            <thead>
                                <tr>
                                    <th scope="col">SKU</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Design</th>
                                    <th scope="col">Weave</th>
                                    <th scope="col">Size</th>
                                    <th scope="col">Color</th>
                                    <th className="r" scope="col">
                                        In stock
                                    </th>
                                    <th className="r" scope="col">
                                        List price
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {filteredCarpets.map((carpet) => (
                                    <tr key={carpet.id}>
                                        <td className="carpets-sku">
                                            {carpet.sku}
                                        </td>
                                        <td className="b">{carpet.name}</td>
                                        <td>{carpet.design}</td>
                                        <td>{carpet.weave}</td>
                                        <td>{carpet.size}</td>
                                        <td>{carpet.color}</td>
                                        <td className="r">
                                            {carpet.in_stock_count}
                                        </td>
                                        <td className="r b">
                                            PKR{' '}
                                            {new Intl.NumberFormat(
                                                'en-US',
                                            ).format(carpet.price)}
                                        </td>
                                    </tr>
                                ))}
                                {filteredCarpets.length === 0 && (
                                    <tr>
                                        <td
                                            className="carpets-empty"
                                            colSpan={8}
                                        >
                                            No carpets match your search.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            {canManage && (
                <Dialog open={dialogOpen} onOpenChange={setOpen}>
                    <DialogContent className="carpet-dialog max-w-[560px] gap-0 overflow-hidden p-0">
                        <DialogHeader className="carpet-dialog-head">
                            <DialogTitle>Add carpet</DialogTitle>
                        </DialogHeader>
                        <form onSubmit={submit}>
                            <div className="carpet-dialog-body">
                                <div className="carpet-form-row">
                                    <div className="carpet-field">
                                        <Label htmlFor="carpet-name">
                                            Name
                                        </Label>
                                        <Input
                                            id="carpet-name"
                                            autoFocus
                                            placeholder="Tabriz Herati"
                                            value={form.data.name}
                                            onChange={(event) =>
                                                form.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors.name,
                                            )}
                                        />
                                        <InputError
                                            message={form.errors.name}
                                        />
                                    </div>
                                    <div className="carpet-field">
                                        <Label htmlFor="carpet-design">
                                            Design
                                        </Label>
                                        <Input
                                            id="carpet-design"
                                            placeholder="Tabriz"
                                            value={form.data.design}
                                            onChange={(event) =>
                                                form.setData(
                                                    'design',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors.design,
                                            )}
                                        />
                                        <InputError
                                            message={form.errors.design}
                                        />
                                    </div>
                                </div>

                                <div className="carpet-form-row">
                                    <div className="carpet-field">
                                        <Label htmlFor="carpet-size">
                                            Size
                                        </Label>
                                        <Input
                                            id="carpet-size"
                                            placeholder="6x9 ft"
                                            value={form.data.size}
                                            onChange={(event) =>
                                                form.setData(
                                                    'size',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors.size,
                                            )}
                                        />
                                        <InputError
                                            message={form.errors.size}
                                        />
                                    </div>
                                    <div className="carpet-field">
                                        <Label htmlFor="carpet-color">
                                            Color
                                        </Label>
                                        <Input
                                            id="carpet-color"
                                            placeholder="Indigo"
                                            value={form.data.color}
                                            onChange={(event) =>
                                                form.setData(
                                                    'color',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors.color,
                                            )}
                                        />
                                        <InputError
                                            message={form.errors.color}
                                        />
                                    </div>
                                </div>

                                <div className="carpet-form-row">
                                    <div className="carpet-field">
                                        <Label htmlFor="carpet-material">
                                            Material
                                        </Label>
                                        <Input
                                            id="carpet-material"
                                            placeholder="Wool and silk"
                                            value={form.data.material}
                                            onChange={(event) =>
                                                form.setData(
                                                    'material',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors.material,
                                            )}
                                        />
                                        <InputError
                                            message={form.errors.material}
                                        />
                                    </div>
                                    <div className="carpet-field">
                                        <Label htmlFor="carpet-price">
                                            List price (PKR)
                                        </Label>
                                        <Input
                                            id="carpet-price"
                                            type="number"
                                            min="0"
                                            step="500"
                                            value={form.data.price}
                                            onChange={(event) =>
                                                form.setData(
                                                    'price',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                form.errors.price,
                                            )}
                                        />
                                        <InputError
                                            message={form.errors.price}
                                        />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter className="carpet-dialog-foot">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setOpen(false)}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    className="demo-primary-button"
                                    disabled={form.processing}
                                >
                                    Save carpet
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}

CarpetsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Carpets',
            href: '/carpets',
        },
    ],
};
