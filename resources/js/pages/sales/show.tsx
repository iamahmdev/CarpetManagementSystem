import { Head, Link, router, usePage } from '@inertiajs/react';
import { Printer, Wallet } from 'lucide-react';
import { useState } from 'react';
import { ReverseReceipt } from '@/pages/customers/ledger';
import {
    PaymentDialog,
    type OpenInvoice,
} from '@/components/ledger/payment-dialog';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/ledger';

type Sale = {
    id: number;
    invoice_number: string;
    customer: { id: number; name: string; phone: string; city: string } | null;
    location: string;
    sold_at: string;
    subtotal: number;
    discount: number;
    total: number;
    paid: number;
    balance: number;
    status: string;
    items: { sku: string | null; name: string; unit_price: number }[];
    payments: {
        id: number;
        receipt_number: string;
        amount: number;
        method: string;
        received_at: string;
        status: string;
        reversal_reason: string | null;
    }[];
    can_receive_payment: boolean;
    can_reverse_payment: boolean;
};

export default function SalesShow({ sale }: { sale: Sale }) {
    const [paymentOpen, setPaymentOpen] = useState(false);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const invoice: OpenInvoice = {
        id: sale.id,
        invoice_number: sale.invoice_number,
        sold_at: sale.sold_at,
        status: sale.status,
        balance: sale.balance,
    };

    function printInvoice() {
        window.print();
    }

    function voidSale() {
        if (window.confirm(`Void invoice ${sale.invoice_number}?`)) {
            router.post(`/sales/${sale.id}/void`);
        }
    }

    return (
        <>
            <Head title={sale.invoice_number} />
            <main className="ledger-page print-surface p-4">
                <header className="page-head no-print">
                    <div>
                        <h1>{sale.invoice_number}</h1>
                        <p className="sub">
                            Invoice · {formatDateTime(sale.sold_at)}
                        </p>
                    </div>
                    <div className="ledger-actions">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={printInvoice}
                        >
                            <Printer /> Print invoice
                        </Button>
                        {sale.can_receive_payment &&
                            sale.balance > 0 &&
                            sale.status !== 'Void' && (
                                <Button
                                    className="demo-primary-button"
                                    onClick={() => setPaymentOpen(true)}
                                >
                                    <Wallet /> Record a payment
                                </Button>
                            )}
                        {sale.can_reverse_payment && sale.status !== 'Void' && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="ledger-danger"
                                onClick={voidSale}
                            >
                                Void sale
                            </Button>
                        )}
                    </div>
                </header>
                {Object.entries(errors).map(([field, message]) => (
                    <p className="ledger-validation-error" key={field}>
                        {message}
                    </p>
                ))}
                <section className="card ledger-card">
                    <div className="ledger-invoice-head">
                        <div>
                            <h2>Carpet system</h2>
                            <p>{sale.location}</p>
                        </div>
                        <div className="ledger-invoice-customer">
                            <strong>{sale.customer?.name ?? 'Walk-in'}</strong>
                            {sale.customer && (
                                <span>
                                    {sale.customer.phone} · {sale.customer.city}
                                </span>
                            )}
                        </div>
                        <span
                            className={`badge ${sale.status === 'Paid' ? 'b-green' : sale.status === 'Partial' ? 'b-amber' : 'b-red'}`}
                        >
                            {sale.status}
                        </span>
                    </div>
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Item</th>
                                    <th className="r">Price</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sale.items.map((item, index) => (
                                    <tr key={`${item.sku}-${index}`}>
                                        <td>{item.sku ?? '—'}</td>
                                        <td>{item.name}</td>
                                        <td className="r">
                                            {formatMoney(item.unit_price)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="ledger-totals">
                        <div>
                            <span>Subtotal</span>
                            <strong>{formatMoney(sale.subtotal)}</strong>
                        </div>
                        <div>
                            <span>Discount</span>
                            <strong>{formatMoney(sale.discount)}</strong>
                        </div>
                        <div className="ledger-total">
                            <span>Total</span>
                            <strong>{formatMoney(sale.total)}</strong>
                        </div>
                        <div>
                            <span>Paid</span>
                            <strong>{formatMoney(sale.paid)}</strong>
                        </div>
                        <div>
                            <span>Balance</span>
                            <strong
                                className={
                                    sale.balance > 0 ? 'ledger-danger' : ''
                                }
                            >
                                {formatMoney(sale.balance)}
                            </strong>
                        </div>
                    </div>
                </section>
                <section className="card ledger-card no-print">
                    <h2>Payments</h2>
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Receipt</th>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th className="r">Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sale.payments.map((payment) => (
                                    <tr key={payment.id}>
                                        <td>
                                            <Link
                                                className="ledger-link"
                                                href={`/receipts/${encodeURIComponent(payment.receipt_number)}`}
                                            >
                                                {payment.receipt_number}
                                            </Link>
                                        </td>
                                        <td>
                                            {formatDateTime(
                                                payment.received_at,
                                            )}
                                        </td>
                                        <td>
                                            {payment.method.replaceAll(
                                                '_',
                                                ' ',
                                            )}
                                        </td>
                                        <td>
                                            <span
                                                className={`badge ${payment.status === 'active' ? 'b-green' : 'b-red'}`}
                                            >
                                                {payment.status === 'active'
                                                    ? 'Active'
                                                    : 'Reversed'}
                                            </span>
                                        </td>
                                        <td className="r">
                                            {formatMoney(payment.amount)}
                                        </td>
                                        <td>
                                            {sale.can_reverse_payment &&
                                                payment.status === 'active' && (
                                                    <ReverseReceipt
                                                        receipt={
                                                            payment.receipt_number
                                                        }
                                                    />
                                                )}
                                        </td>
                                    </tr>
                                ))}
                                {sale.payments.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="empty">
                                            No payments recorded.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            {sale.can_receive_payment && (
                <PaymentDialog
                    open={paymentOpen}
                    onOpenChange={setPaymentOpen}
                    action={`/sales/${sale.id}/payments`}
                    invoices={sale.balance > 0 ? [invoice] : []}
                    maxAmount={sale.balance}
                    allowBackdate={sale.can_reverse_payment}
                />
            )}
        </>
    );
}
