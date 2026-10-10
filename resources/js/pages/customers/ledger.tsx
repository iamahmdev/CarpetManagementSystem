import { Head, Link, router, useForm } from '@inertiajs/react';
import { Printer, Wallet } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    PaymentDialog,
    type OpenInvoice,
} from '@/components/ledger/payment-dialog';
import { formatDateTime, formatDay, formatMoney } from '@/lib/ledger';

type StatementRow = {
    at: string;
    type: 'Invoice' | 'Payment' | 'Payment reversed' | 'Void';
    reference: string;
    method: string | null;
    debit: number;
    credit: number;
    running_balance: number;
    sale_id: number;
    receipt_number: string | null;
    reversed?: boolean;
    reversal_reason: string | null;
};

type Customer = {
    id: number;
    name: string;
    phone: string;
    city: string;
};

export function ReverseReceipt({ receipt }: { receipt: string }) {
    const [expanded, setExpanded] = useState(false);
    const form = useForm({ reason: '' });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({ reason: data.reason.trim() }));
        form.post(`/receipts/${encodeURIComponent(receipt)}/reverse`, {
            onSuccess: () => setExpanded(false),
        });
    }

    if (!expanded) {
        return (
            <Button
                type="button"
                variant="ghost"
                className="ledger-danger"
                onClick={() => setExpanded(true)}
            >
                Reverse
            </Button>
        );
    }

    return (
        <form className="ledger-reverse-form" onSubmit={submit}>
            <Label htmlFor={`reason-${receipt}`}>Reason for reversal</Label>
            <Input
                id={`reason-${receipt}`}
                required
                value={form.data.reason}
                onChange={(event) => form.setData('reason', event.target.value)}
            />
            <InputError message={form.errors.reason} />
            <div className="ledger-inline-actions">
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={form.processing}
                    onClick={(event) => {
                        if (
                            !window.confirm(
                                `Reverse receipt ${receipt}? This cannot be undone.`,
                            )
                        ) {
                            event.preventDefault();
                        }
                    }}
                >
                    Confirm reversal
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    onClick={() => setExpanded(false)}
                >
                    Cancel
                </Button>
            </div>
        </form>
    );
}

type Props = {
    customer: Customer;
    metrics: {
        total_invoiced: number;
        total_paid: number;
        balance_due: number;
        oldest_age_days: number;
    };
    statement: StatementRow[];
    unpaid_invoices: OpenInvoice[];
    can_receive_payment: boolean;
    can_reverse_payment: boolean;
    allow_backdate: boolean;
};

export default function CustomerLedger(props: Props) {
    const [paymentOpen, setPaymentOpen] = useState(false);

    function printStatement() {
        router.post(
            `/customers/${props.customer.id}/statement-print`,
            {},
            {
                onSuccess: () => window.print(),
            },
        );
    }

    return (
        <>
            <Head title={`${props.customer.name} ledger`} />
            <main className="ledger-page print-surface p-4">
                <header className="page-head no-print">
                    <div>
                        <h1>{props.customer.name}</h1>
                        <p className="sub">
                            {props.customer.phone} · {props.customer.city}
                        </p>
                    </div>
                    <div className="ledger-actions">
                        {props.can_receive_payment &&
                            props.metrics.balance_due > 0 && (
                                <Button
                                    className="demo-primary-button"
                                    onClick={() => setPaymentOpen(true)}
                                >
                                    <Wallet />
                                    Receive payment
                                </Button>
                            )}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={printStatement}
                        >
                            <Printer />
                            Print statement
                        </Button>
                    </div>
                </header>
                <div className="ledger-metrics">
                    <section className="card metric">
                        <div className="l">Total invoiced</div>
                        <div className="v">
                            {formatMoney(props.metrics.total_invoiced)}
                        </div>
                    </section>
                    <section className="card metric">
                        <div className="l">Total paid</div>
                        <div className="v">
                            {formatMoney(props.metrics.total_paid)}
                        </div>
                    </section>
                    <section className="card metric">
                        <div className="l">Balance due</div>
                        <div className="v ledger-danger">
                            {formatMoney(props.metrics.balance_due)}
                        </div>
                    </section>
                    <section className="card metric">
                        <div className="l">Oldest unpaid invoice age</div>
                        <div className="v">
                            {props.metrics.oldest_age_days} days
                        </div>
                    </section>
                </div>

                <section
                    className="card ledger-card"
                    aria-label="Customer statement"
                >
                    <div className="card-head">
                        <h2>Statement</h2>
                        <span className="muted">
                            {props.customer.name} · {props.customer.phone} ·{' '}
                            {props.customer.city}
                        </span>
                    </div>
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reference</th>
                                    <th>Method</th>
                                    <th className="r">Debit</th>
                                    <th className="r">Credit</th>
                                    <th className="r">Running balance</th>
                                    {props.can_reverse_payment && (
                                        <th className="no-print">Actions</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {props.statement.map((row, index) => (
                                    <tr
                                        key={`${row.reference}-${row.type}-${index}`}
                                    >
                                        <td>{formatDateTime(row.at)}</td>
                                        <td>
                                            <span
                                                className={`badge ${row.type === 'Payment' ? 'b-green' : row.type === 'Payment reversed' || row.type === 'Void' ? 'b-red' : 'b-indigo'}`}
                                            >
                                                {row.type}
                                            </span>
                                        </td>
                                        <td>
                                            {row.receipt_number ? (
                                                <Link
                                                    className="ledger-link"
                                                    href={`/receipts/${encodeURIComponent(row.receipt_number)}`}
                                                >
                                                    {row.reference}
                                                </Link>
                                            ) : (
                                                <Link
                                                    className="ledger-link"
                                                    href={`/sales/${row.sale_id}`}
                                                >
                                                    {row.reference}
                                                </Link>
                                            )}
                                            {row.reversal_reason && (
                                                <div className="muted ledger-note">
                                                    {row.reversal_reason}
                                                </div>
                                            )}
                                        </td>
                                        <td>
                                            {row.method?.replaceAll('_', ' ') ??
                                                '—'}
                                        </td>
                                        <td className="r">
                                            {row.debit > 0
                                                ? formatMoney(row.debit)
                                                : '—'}
                                        </td>
                                        <td className="r">
                                            {row.credit > 0
                                                ? formatMoney(row.credit)
                                                : '—'}
                                        </td>
                                        <td
                                            className={`r b ${row.running_balance > 0 ? 'ledger-danger' : 'muted'}`}
                                        >
                                            {formatMoney(row.running_balance)}
                                        </td>
                                        {props.can_reverse_payment && (
                                            <td className="no-print">
                                                {row.type === 'Payment' &&
                                                    row.receipt_number &&
                                                    !row.reversed && (
                                                        <ReverseReceipt
                                                            receipt={
                                                                row.receipt_number
                                                            }
                                                        />
                                                    )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {props.statement.length === 0 && (
                                    <tr>
                                        <td className="empty" colSpan={8}>
                                            No statement activity.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section
                    className="card ledger-card"
                    aria-label="Unpaid invoices"
                >
                    <div className="card-head">
                        <h2>Unpaid invoices</h2>
                    </div>
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th className="r">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {props.unpaid_invoices.map((invoice) => (
                                    <tr key={invoice.id}>
                                        <td className="b">
                                            <Link
                                                className="ledger-link"
                                                href={`/sales/${invoice.id}`}
                                            >
                                                {invoice.invoice_number}
                                            </Link>
                                        </td>
                                        <td>{formatDay(invoice.sold_at)}</td>
                                        <td>
                                            <span className="badge b-amber">
                                                {invoice.status}
                                            </span>
                                        </td>
                                        <td className="r b ledger-danger">
                                            {formatMoney(invoice.balance)}
                                        </td>
                                    </tr>
                                ))}
                                {props.unpaid_invoices.length === 0 && (
                                    <tr>
                                        <td className="empty" colSpan={4}>
                                            No unpaid invoices.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            {props.can_receive_payment && (
                <PaymentDialog
                    open={paymentOpen}
                    onOpenChange={setPaymentOpen}
                    action={`/customers/${props.customer.id}/payments`}
                    invoices={props.unpaid_invoices}
                    maxAmount={props.metrics.balance_due}
                    allowBackdate={props.allow_backdate}
                />
            )}
            <div className="print-only ledger-print-heading">
                <h1>Carpet system — Customer statement</h1>
                <p>
                    {props.customer.name} · {props.customer.phone} ·{' '}
                    {props.customer.city}
                </p>
            </div>
        </>
    );
}
