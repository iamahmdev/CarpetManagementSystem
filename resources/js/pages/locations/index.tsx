import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
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

type LocationType = 'warehouse' | 'display';

type LocationRow = {
    id: number;
    name: string;
    type: LocationType;
    city: string;
    adjacent_warehouse: string | null;
    pieces_count: number;
};

type WarehouseOption = {
    id: number;
    name: string;
};

type Props = {
    locations: LocationRow[];
    warehouses: WarehouseOption[];
};

export default function LocationsIndex({ locations, warehouses }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm<{
        name: string;
        type: LocationType;
        city: string;
        parent_id: number | null;
    }>({
        name: '',
        type: 'display',
        city: '',
        parent_id: null,
    });

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
            city: data.city.trim(),
            parent_id: data.type === 'display' ? data.parent_id : null,
        }));
        form.post('/locations', {
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        });
    }

    return (
        <>
            <Head title="Locations" />
            <main className="locations-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Locations</h1>
                        <p className="sub">
                            Warehouses and displays. A new city is just a new
                            row, with no code changes.
                        </p>
                    </div>
                    <div className="head-actions">
                        <Button
                            type="button"
                            className="demo-primary-button"
                            onClick={() => setOpen(true)}
                        >
                            <Plus />
                            Add location
                        </Button>
                    </div>
                </header>

                <section className="locations-card" aria-label="Locations list">
                    <div className="locations-table-wrap">
                        <table className="locations-table">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">City</th>
                                    <th scope="col">Adjacent warehouse</th>
                                    <th className="r" scope="col">
                                        Pieces
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {locations.map((location) => (
                                    <tr key={location.id}>
                                        <td className="b">{location.name}</td>
                                        <td>
                                            <span
                                                className={`badge ${
                                                    location.type ===
                                                    'warehouse'
                                                        ? 'b-indigo'
                                                        : 'b-amber'
                                                }`}
                                            >
                                                {location.type === 'warehouse'
                                                    ? 'Warehouse'
                                                    : 'Display'}
                                            </span>
                                        </td>
                                        <td>{location.city}</td>
                                        <td>
                                            {location.adjacent_warehouse ?? (
                                                <span className="muted">
                                                    None
                                                </span>
                                            )}
                                        </td>
                                        <td className="r">
                                            {location.pieces_count}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <Dialog open={dialogOpen} onOpenChange={setOpen}>
                <DialogContent className="location-dialog max-w-[560px] gap-0 overflow-hidden p-0">
                    <DialogHeader className="location-dialog-head">
                        <DialogTitle>Add location</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit}>
                        <div className="location-dialog-body">
                            <div className="location-field">
                                <Label htmlFor="location-name">Name</Label>
                                <Input
                                    id="location-name"
                                    autoFocus
                                    placeholder="Peshawar Display"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    aria-invalid={Boolean(form.errors.name)}
                                />
                                <InputError message={form.errors.name} />
                            </div>

                            <div className="location-form-row">
                                <div className="location-field">
                                    <Label htmlFor="location-type">Type</Label>
                                    <select
                                        id="location-type"
                                        value={form.data.type}
                                        onChange={(event) => {
                                            const type =
                                                event.target.value ===
                                                'warehouse'
                                                    ? 'warehouse'
                                                    : 'display';

                                            form.setData('type', type);
                                        }}
                                        aria-invalid={Boolean(form.errors.type)}
                                    >
                                        <option value="display">Display</option>
                                        <option value="warehouse">
                                            Warehouse
                                        </option>
                                    </select>
                                    <InputError message={form.errors.type} />
                                </div>
                                <div className="location-field">
                                    <Label htmlFor="location-city">City</Label>
                                    <Input
                                        id="location-city"
                                        placeholder="Peshawar"
                                        value={form.data.city}
                                        onChange={(event) =>
                                            form.setData(
                                                'city',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.city)}
                                    />
                                    <InputError message={form.errors.city} />
                                </div>
                            </div>

                            <div className="location-field">
                                <Label htmlFor="location-parent">
                                    Adjacent warehouse (for a display)
                                </Label>
                                <select
                                    id="location-parent"
                                    value={
                                        form.data.parent_id?.toString() ?? ''
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'parent_id',
                                            event.target.value
                                                ? Number(event.target.value)
                                                : null,
                                        )
                                    }
                                    aria-invalid={Boolean(
                                        form.errors.parent_id,
                                    )}
                                >
                                    <option value="">None</option>
                                    {warehouses.map((warehouse) => (
                                        <option
                                            key={warehouse.id}
                                            value={warehouse.id}
                                        >
                                            {warehouse.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.parent_id} />
                            </div>
                        </div>
                        <DialogFooter className="location-dialog-foot">
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
                                Save location
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

LocationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Locations',
            href: '/locations',
        },
    ],
};
