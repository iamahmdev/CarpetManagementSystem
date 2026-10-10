import { Head, Link, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { ReverseReceipt } from '@/pages/customers/ledger';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/ledger';

type Receipt = {
    number: string;
    received_at: string;
    customer: { id: number; name: string; phone: string; city: string } | null;
    location: string;
    received_by: string;
    method: string;
    note: string | null;
    total: number;
    balance_after: number | null;
    reversed: boolean;
    can_reverse_payment: boolean;
    reversal_reason: string | null;
    allocations: { invoice_number: string; amount: number }[];
};

export default function PaymentReceipt({ receipt }: { receipt: Receipt }) {
    function printReceipt() {
        router.post(
            `/receipts/${encodeURIComponent(receipt.number)}/print`,
            {},
            {
                onSuccess: () => window.print(),
            },
        );
    }

    return (
        <>
            <Head title={`Receipt ${receipt.number}`} />
            <main className="ledger-page print-surface p-4">
                <header className="page-head no-print">
                    <div>
                        <h1>Payment receipt</h1>
                        <p className="sub">{receipt.number}</p>
                    </div>
                    <div className="ledger-actions">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={printReceipt}
                        >
                            <Printer /> Print receipt
                        </Button>
                        {receipt.customer && (
                            <Link
                                className="btn"
                                href={`/customers/${receipt.customer.id}`}
                            >
                                Customer ledger
                            </Link>
                        )}
                        {receipt.can_reverse_payment && !receipt.reversed && (
                            <ReverseReceipt receipt={receipt.number} />
                        )}
                    </div>
                </header>
                <section className="card ledger-card ledger-receipt">
                    <div className="ledger-invoice-head">
                        <div>
                            <h2>Carpet system</h2>
                            <p>{receipt.location}</p>
                        </div>
                        {receipt.reversed && (
                            <span className="badge b-red">REVERSED</span>
                        )}
                    </div>
                    {receipt.reversed && (
                        <p className="ledger-reversal-mark">REVERSED</p>
                    )}
                    <dl className="ledger-receipt-details">
                        <div>
                            <dt>Receipt</dt>
                            <dd>{receipt.number}</dd>
                        </div>
                        <div>
                            <dt>Date</dt>
                            <dd>{formatDateTime(receipt.received_at)}</dd>
                        </div>
                        <div>
                            <dt>Customer</dt>
                            <dd>{receipt.customer?.name ?? 'Walk-in'}</dd>
                        </div>
                        <div>
                            <dt>Received by</dt>
                            <dd>{receipt.received_by}</dd>
                        </div>
                        <div>
                            <dt>Method</dt>
                            <dd>{receipt.method.replaceAll('_', ' ')}</dd>
                        </div>
                        {receipt.note && (
                            <div>
                                <dt>Note</dt>
                                <dd>{receipt.note}</dd>
                            </div>
                        )}
                    </dl>
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th className="r">Applied</th>
                                </tr>
                            </thead>
                            <tbody>
                                {receipt.allocations.map((allocation) => (
                                    <tr key={allocation.invoice_number}>
                                        <td>{allocation.invoice_number}</td>
                                        <td className="r">
                                            {formatMoney(allocation.amount)}
                                        </td>
                                    </tr>
                                ))}
                                <tr>
                                    <td className="b">Total received</td>
                                    <td className="r b">
                                        {formatMoney(receipt.total)}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    {receipt.customer && receipt.balance_after !== null && (
                        <p className="ledger-receipt-balance">
                            Customer balance after payment:{' '}
                            <strong>
                                {formatMoney(receipt.balance_after)}
                            </strong>
                        </p>
                    )}
                    {receipt.reversal_reason && (
                        <p className="ledger-danger">
                            Reversal reason: {receipt.reversal_reason}
                        </p>
                    )}
                </section>
            </main>
        </>
    );
}
