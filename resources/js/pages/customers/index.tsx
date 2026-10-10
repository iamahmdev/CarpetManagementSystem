import { Head, Link, useForm } from '@inertiajs/react';
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
import { formatMoney } from '@/lib/ledger';

type CustomerRow = {
    id: number;
    name: string;
    phone: string;
    city: string;
    invoices_count: number;
    purchased_total: number;
    balance_due: number;
};

export default function CustomersIndex({
    customers,
    can_create,
}: {
    customers: CustomerRow[];
    can_create: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ name: '', phone: '', city: '' });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name.trim(),
            phone: data.phone.trim(),
            city: data.city.trim(),
        }));
        form.post('/customers', {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }

    return (
        <>
            <Head title="Customers" />
            <main className="ledger-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Customers</h1>
                        <p className="sub">
                            Buyers, their purchases, and any balance still due.
                        </p>
                    </div>
                    {can_create && (
                        <Button
                            className="demo-primary-button"
                            onClick={() => setOpen(true)}
                        >
                            <Plus />
                            Add customer
                        </Button>
                    )}
                </header>
                <section className="card" aria-label="Customers">
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                    <th className="r">Invoices</th>
                                    <th className="r">Purchased</th>
                                    <th className="r">Balance due</th>
                                </tr>
                            </thead>
                            <tbody>
                                {customers.map((customer) => (
                                    <tr key={customer.id}>
                                        <td className="b">
                                            <Link
                                                className="ledger-link"
                                                href={`/customers/${customer.id}`}
                                            >
                                                {customer.name}
                                            </Link>
                                        </td>
                                        <td>{customer.phone}</td>
                                        <td>{customer.city}</td>
                                        <td className="r">
                                            {customer.invoices_count}
                                        </td>
                                        <td className="r">
                                            {formatMoney(
                                                customer.purchased_total,
                                            )}
                                        </td>
                                        <td
                                            className={`r b ${customer.balance_due > 0 ? 'ledger-danger' : 'muted'}`}
                                        >
                                            {formatMoney(customer.balance_due)}
                                        </td>
                                    </tr>
                                ))}
                                {customers.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="empty">
                                            No customers yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            {can_create && (
                <Dialog open={open} onOpenChange={setOpen}>
                    <DialogContent className="ledger-dialog max-w-[560px] gap-0 overflow-hidden p-0">
                        <DialogHeader className="ledger-dialog-head">
                            <DialogTitle>Add customer</DialogTitle>
                        </DialogHeader>
                        <form onSubmit={submit}>
                            <div className="ledger-dialog-body">
                                <div className="ledger-field">
                                    <Label htmlFor="customer-name">Name</Label>
                                    <Input
                                        id="customer-name"
                                        autoFocus
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(form.errors.name)}
                                    />
                                    <InputError message={form.errors.name} />
                                </div>
                                <div className="ledger-form-grid">
                                    <div className="ledger-field">
                                        <Label htmlFor="customer-phone">
                                            Phone
                                        </Label>
                                        <Input
                                            id="customer-phone"
                                            value={form.data.phone}
                                            onChange={(event) =>
                                                form.setData(
                                                    'phone',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="ledger-field">
                                        <Label htmlFor="customer-city">
                                            City
                                        </Label>
                                        <Input
                                            id="customer-city"
                                            value={form.data.city}
                                            onChange={(event) =>
                                                form.setData(
                                                    'city',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>
                            <DialogFooter className="ledger-dialog-footer">
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
                                    Save customer
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </>
    );
}
