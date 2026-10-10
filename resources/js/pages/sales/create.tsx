import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';
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

type Props = {
    customers: { id: number; name: string }[];
    locations: { id: number; name: string }[];
    pieces: {
        id: number;
        code: string;
        sku: string;
        carpet: string;
        size: string;
        location_id: number;
        price: number;
    }[];
    payment_methods: { value: string; label: string }[];
    selected_customer_id: number | null;
    selected_piece_id: number | null;
};

export default function SalesCreate({
    customers,
    locations,
    pieces,
    payment_methods,
    selected_customer_id,
    selected_piece_id,
}: Props) {
    const [customerOpen, setCustomerOpen] = useState(false);
    const selectedPiece = pieces.find(
        (piece) => piece.id === selected_piece_id,
    );
    const form = useForm<{
        customer_id: string;
        location_id: string;
        discount: string;
        paid_now: string;
        method: string;
        items: { piece_id: string; unit_price: string }[];
    }>({
        customer_id: selected_customer_id ? String(selected_customer_id) : '',
        location_id: selectedPiece
            ? String(selectedPiece.location_id)
            : locations[0]
              ? String(locations[0].id)
              : '',
        discount: '0',
        paid_now: '',
        method: 'cash',
        items: selectedPiece
            ? [
                  {
                      piece_id: String(selectedPiece.id),
                      unit_price: String(selectedPiece.price),
                  },
              ]
            : [],
    });
    const customerForm = useForm({
        name: '',
        phone: '',
        city: '',
        return_to_sale: true,
    });
    const subtotal = useMemo(
        () =>
            form.data.items.reduce(
                (sum, item) => sum + (Number(item.unit_price) || 0),
                0,
            ),
        [form.data.items],
    );
    const discount = Math.min(
        Math.max(0, Number(form.data.discount) || 0),
        subtotal,
    );
    const total = subtotal - discount;

    function createCustomer(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        customerForm.transform((data) => ({
            ...data,
            name: data.name.trim(),
            phone: data.phone.trim(),
            city: data.city.trim(),
        }));
        customerForm.post('/customers', {
            onSuccess: () => setCustomerOpen(false),
        });
    }

    function addItem() {
        const piece = pieces.find(
            (row) =>
                row.location_id === Number(form.data.location_id) &&
                !form.data.items.some((item) => item.piece_id === String(row.id)),
        );
        if (!piece) return;
        form.setData('items', [
            ...form.data.items,
            { piece_id: String(piece.id), unit_price: String(piece.price) },
        ]);
    }

    function updateItem(
        index: number,
        field: 'piece_id' | 'unit_price',
        value: string,
    ) {
        const items = form.data.items.map((item, itemIndex) =>
            itemIndex === index ? { ...item, [field]: value } : item,
        );
        if (field === 'piece_id') {
            const piece = pieces.find((row) => String(row.id) === value);
            if (piece) items[index].unit_price = String(piece.price);
        }
        form.setData('items', items);
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            customer_id: data.customer_id || '',
            discount: data.discount.trim(),
            paid_now: data.paid_now.trim(),
            items: data.items.map((item) => ({
                piece_id: item.piece_id,
                unit_price: item.unit_price.trim(),
            })),
        }));
        form.post('/sales');
    }

    return (
        <>
            <Head title="New sale" />
            <main className="ledger-page p-4">
                <header className="page-head">
                    <div>
                        <h1>New sale</h1>
                        <p className="sub">
                            Pick the display, add pieces, take payment, and
                            print the invoice.
                        </p>
                    </div>
                </header>
                <form className="ledger-sale-form" onSubmit={submit}>
                    <section className="card ledger-card">
                        <div className="ledger-form-grid">
                            <div className="ledger-field">
                                <Label htmlFor="sale-customer">Customer</Label>
                                <div className="ledger-customer-select">
                                    <select
                                        id="sale-customer"
                                        value={form.data.customer_id}
                                        onChange={(event) =>
                                            form.setData(
                                                'customer_id',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Walk-in</option>
                                        {customers.map((customer) => (
                                            <option
                                                key={customer.id}
                                                value={customer.id}
                                            >
                                                {customer.name}
                                            </option>
                                        ))}
                                    </select>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCustomerOpen(true)}
                                    >
                                        <Plus /> Add
                                    </Button>
                                </div>
                                <InputError message={form.errors.customer_id} />
                            </div>
                            <div className="ledger-field">
                                <Label htmlFor="sale-location">
                                    Display location
                                </Label>
                                <select
                                    id="sale-location"
                                    required
                                    value={form.data.location_id}
                                    onChange={(event) => {
                                        form.setData(
                                            'location_id',
                                            event.target.value,
                                        );
                                        form.setData('items', []);
                                    }}
                                >
                                    {locations.map((location) => (
                                        <option
                                            key={location.id}
                                            value={location.id}
                                        >
                                            {location.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.location_id} />
                            </div>
                        </div>
                        <div className="card-head">
                            <h2>Sale items</h2>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={addItem}
                                disabled={!pieces.some(
                                    (piece) =>
                                        piece.location_id === Number(form.data.location_id),
                                )}
                            >
                                <Plus /> Add piece
                            </Button>
                        </div>
                        <div className="tbl-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Piece</th>
                                        <th className="r">Line price (PKR)</th>
                                        <th aria-label="Actions" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {form.data.items.map((item, index) => (
                                        <tr key={`${index}-${item.piece_id}`}>
                                            <td>
                                                <select
                                                    aria-label="Piece"
                                                    value={item.piece_id}
                                                    onChange={(event) =>
                                                        updateItem(
                                                            index,
                                                            'piece_id',
                                                            event.target.value,
                                                        )
                                                    }
                                                >
                                                    {pieces.filter((piece) =>
                                                        piece.location_id === Number(form.data.location_id) &&
                                                        (item.piece_id === String(piece.id) ||
                                                            !form.data.items.some(
                                                                (selected, selectedIndex) =>
                                                                    selectedIndex !== index &&
                                                                    selected.piece_id === String(piece.id),
                                                            )),
                                                    ).map((piece) => (
                                                        <option
                                                            key={piece.id}
                                                            value={piece.id}
                                                        >
                                                            {piece.code} · {piece.carpet} · {piece.size}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className="r">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    step="500"
                                                    value={item.unit_price}
                                                    onChange={(event) =>
                                                        updateItem(
                                                            index,
                                                            'unit_price',
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    aria-label="Remove item"
                                                    onClick={() =>
                                                        form.setData(
                                                            'items',
                                                            form.data.items.filter(
                                                                (
                                                                    _,
                                                                    itemIndex,
                                                                ) =>
                                                                    itemIndex !==
                                                                    index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {form.data.items.length === 0 && (
                                        <tr>
                                            <td className="empty" colSpan={3}>
                                                {pieces.some((piece) =>
                                                    piece.location_id === Number(form.data.location_id),
                                                )
                                                    ? 'Add a piece to start the sale.'
                                                    : 'No in-stock pieces are available at this display.'}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        <InputError message={form.errors.items} />
                        <div className="ledger-totals">
                            <div>
                                <span>Subtotal</span>
                                <strong>{formatMoney(subtotal)}</strong>
                            </div>
                            <div className="ledger-form-grid">
                                <div className="ledger-field">
                                    <Label htmlFor="sale-discount">
                                        Discount (PKR)
                                    </Label>
                                    <Input
                                        id="sale-discount"
                                        type="number"
                                        min="0"
                                        step="500"
                                        value={form.data.discount}
                                        onChange={(event) =>
                                            form.setData(
                                                'discount',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="ledger-total">
                                    <span>Total</span>
                                    <strong>{formatMoney(total)}</strong>
                                </div>
                            </div>
                        </div>
                    </section>
                    <section className="card ledger-card">
                        <h2>Payment</h2>
                        <div className="ledger-form-grid">
                            <div className="ledger-field">
                                <Label htmlFor="sale-paid">
                                    Amount paid now (PKR)
                                </Label>
                                <Input
                                    id="sale-paid"
                                    type="number"
                                    min="0"
                                    max={total}
                                    step="500"
                                    value={form.data.paid_now || total}
                                    onChange={(event) =>
                                        form.setData(
                                            'paid_now',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError message={form.errors.paid_now} />
                            </div>
                            <div className="ledger-field">
                                <Label htmlFor="sale-method">Method</Label>
                                <select
                                    id="sale-method"
                                    value={form.data.method}
                                    onChange={(event) =>
                                        form.setData(
                                            'method',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {payment_methods.map((method) => (
                                        <option
                                            key={method.value}
                                            value={method.value}
                                        >
                                            {method.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        {Number(form.data.paid_now || total) < total && (
                            <p className="ledger-danger">
                                A balance due requires a named customer.
                            </p>
                        )}
                        <div className="ledger-form-actions">
                            <Button
                                type="submit"
                                className="demo-primary-button"
                                disabled={
                                    form.processing ||
                                    !locations.length ||
                                    !form.data.items.length
                                }
                            >
                                Save sale
                            </Button>
                        </div>
                    </section>
                </form>
            </main>
            <Dialog open={customerOpen} onOpenChange={setCustomerOpen}>
                <DialogContent className="ledger-dialog max-w-[560px] gap-0 overflow-hidden p-0">
                    <DialogHeader className="ledger-dialog-head">
                        <DialogTitle>Add customer</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={createCustomer}>
                        <div className="ledger-dialog-body">
                            <div className="ledger-field">
                                <Label htmlFor="new-customer-name">Name</Label>
                                <Input
                                    id="new-customer-name"
                                    autoFocus
                                    value={customerForm.data.name}
                                    onChange={(event) =>
                                        customerForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={customerForm.errors.name}
                                />
                            </div>
                            <div className="ledger-form-grid">
                                <div className="ledger-field">
                                    <Label htmlFor="new-customer-phone">
                                        Phone
                                    </Label>
                                    <Input
                                        id="new-customer-phone"
                                        value={customerForm.data.phone}
                                        onChange={(event) =>
                                            customerForm.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="ledger-field">
                                    <Label htmlFor="new-customer-city">
                                        City
                                    </Label>
                                    <Input
                                        id="new-customer-city"
                                        value={customerForm.data.city}
                                        onChange={(event) =>
                                            customerForm.setData(
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
                                onClick={() => setCustomerOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                className="demo-primary-button"
                                disabled={customerForm.processing}
                            >
                                Save customer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
