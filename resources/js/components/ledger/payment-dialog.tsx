import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
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
import { formatMoney, localDateTimeInput } from '@/lib/ledger';

export type OpenInvoice = {
    id: number;
    invoice_number: string;
    sold_at: string;
    status: string;
    balance: number;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    action: string;
    invoices: OpenInvoice[];
    maxAmount: number;
    allowBackdate: boolean;
};

function allocateOldest(amount: number, invoices: OpenInvoice[]) {
    let remaining = amount;
    const allocations: Record<string, string> = {};
    for (const invoice of invoices) {
        const allocated = Math.min(remaining, invoice.balance);
        if (allocated > 0) allocations[String(invoice.id)] = String(allocated);
        remaining -= allocated;
        if (remaining <= 0) break;
    }
    return allocations;
}

export function PaymentDialog({
    open,
    onOpenChange,
    action,
    invoices,
    maxAmount,
    allowBackdate,
}: Props) {
    const form = useForm<{
        amount: string;
        method: string;
        received_at: string;
        note: string;
        allocations: Record<string, string>;
    }>({
        amount: '',
        method: 'cash',
        received_at: localDateTimeInput(),
        note: '',
        allocations: {},
    });

    useEffect(() => {
        const amount = Number(form.data.amount) || 0;
        form.setData('allocations', allocateOldest(amount, invoices));
    }, [form.data.amount, invoices]);

    function setOpen(value: boolean) {
        onOpenChange(value);
        if (value) {
            form.reset();
            form.clearErrors();
            form.setData('received_at', localDateTimeInput());
        }
    }

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            amount: data.amount.trim(),
            allocations: Object.fromEntries(
                Object.entries(data.allocations).map(([key, value]) => [
                    key,
                    value === '' ? 0 : Number(value),
                ]),
            ),
        }));
        form.post(action, {
            onSuccess: () => {
                onOpenChange(false);
                form.reset();
            },
        });
    }

    const allocated = Object.values(form.data.allocations).reduce(
        (total, amount) => total + (Number(amount) || 0),
        0,
    );

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="ledger-dialog max-w-[650px] gap-0 overflow-hidden p-0">
                <DialogHeader className="ledger-dialog-head">
                    <DialogTitle>Receive payment</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit}>
                    <div className="ledger-dialog-body">
                        <div className="ledger-form-grid">
                            <div className="ledger-field">
                                <Label htmlFor="payment-amount">
                                    Amount (PKR)
                                </Label>
                                <Input
                                    id="payment-amount"
                                    type="number"
                                    min="1"
                                    max={maxAmount}
                                    step="500"
                                    value={form.data.amount}
                                    onChange={(event) =>
                                        form.setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={Boolean(form.errors.amount)}
                                />
                                <InputError message={form.errors.amount} />
                            </div>
                            <div className="ledger-field">
                                <Label htmlFor="payment-method">Method</Label>
                                <select
                                    id="payment-method"
                                    value={form.data.method}
                                    onChange={(event) =>
                                        form.setData(
                                            'method',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">
                                        Bank transfer
                                    </option>
                                    <option value="cheque">Cheque</option>
                                    <option value="mobile_wallet">
                                        Mobile wallet
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div className="ledger-field">
                            <Label htmlFor="payment-date">Date</Label>
                            <Input
                                id="payment-date"
                                type="datetime-local"
                                max={localDateTimeInput()}
                                min={
                                    allowBackdate
                                        ? undefined
                                        : localDateTimeInput()
                                }
                                value={form.data.received_at}
                                onChange={(event) =>
                                    form.setData(
                                        'received_at',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={Boolean(form.errors.received_at)}
                            />
                            <InputError message={form.errors.received_at} />
                        </div>
                        <div className="ledger-field">
                            <Label htmlFor="payment-note">
                                Note (optional)
                            </Label>
                            <Input
                                id="payment-note"
                                value={form.data.note}
                                onChange={(event) =>
                                    form.setData('note', event.target.value)
                                }
                            />
                        </div>
                        <div className="ledger-allocation">
                            <div className="ledger-allocation-title">
                                <strong>Invoice allocation</strong>
                                <span>
                                    {formatMoney(allocated)} of{' '}
                                    {formatMoney(Number(form.data.amount) || 0)}
                                </span>
                            </div>
                            <div className="tbl-wrap">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Invoice</th>
                                            <th className="r">Balance</th>
                                            <th className="r">Apply</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {invoices.map((invoice) => (
                                            <tr key={invoice.id}>
                                                <td>
                                                    {invoice.invoice_number}
                                                </td>
                                                <td className="r">
                                                    {formatMoney(
                                                        invoice.balance,
                                                    )}
                                                </td>
                                                <td className="r">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={invoice.balance}
                                                        step="1"
                                                        aria-label={`Allocation for ${invoice.invoice_number}`}
                                                        value={
                                                            form.data
                                                                .allocations[
                                                                String(
                                                                    invoice.id,
                                                                )
                                                            ] ?? ''
                                                        }
                                                        onChange={(event) =>
                                                            form.setData(
                                                                'allocations',
                                                                {
                                                                    ...form.data
                                                                        .allocations,
                                                                    [String(
                                                                        invoice.id,
                                                                    )]:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                        {invoices.length === 0 && (
                                            <tr>
                                                <td
                                                    colSpan={3}
                                                    className="empty"
                                                >
                                                    No unpaid invoices.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            <InputError message={form.errors.allocations} />
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
                            disabled={
                                form.processing ||
                                allocated !== Number(form.data.amount)
                            }
                        >
                            Save payment
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
