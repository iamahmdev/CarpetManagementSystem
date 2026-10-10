import { Head, Link } from '@inertiajs/react';
import { formatDateTime, formatMoney } from '@/lib/ledger';

type SaleRow = {
    id: number;
    invoice_number: string;
    customer: string;
    location: string;
    sold_at: string;
    total: number;
    paid: number;
    balance: number;
    status: string;
};

export default function SalesIndex({ sales }: { sales: SaleRow[] }) {
    return (
        <>
            <Head title="Sales history" />
            <main className="ledger-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Sales History</h1>
                        <p className="sub">All invoices across the displays.</p>
                    </div>
                    <Link href="/new-sale" className="btn primary">
                        New sale
                    </Link>
                </header>
                <section className="card">
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Location</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th className="r">Total</th>
                                    <th className="r">Paid</th>
                                    <th className="r">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                {sales.map((sale) => (
                                    <tr key={sale.id}>
                                        <td className="b">
                                            <Link
                                                className="ledger-link"
                                                href={`/sales/${sale.id}`}
                                            >
                                                {sale.invoice_number}
                                            </Link>
                                        </td>
                                        <td>{sale.customer}</td>
                                        <td>{sale.location}</td>
                                        <td>{formatDateTime(sale.sold_at)}</td>
                                        <td>
                                            <span
                                                className={`badge ${sale.status === 'Paid' ? 'b-green' : sale.status === 'Partial' ? 'b-amber' : sale.status === 'Void' ? 'b-red' : 'b-red'}`}
                                            >
                                                {sale.status}
                                            </span>
                                        </td>
                                        <td className="r">
                                            {formatMoney(sale.total)}
                                        </td>
                                        <td className="r">
                                            {formatMoney(sale.paid)}
                                        </td>
                                        <td
                                            className={`r b ${sale.balance > 0 ? 'ledger-danger' : 'muted'}`}
                                        >
                                            {formatMoney(sale.balance)}
                                        </td>
                                    </tr>
                                ))}
                                {sales.length === 0 && (
                                    <tr>
                                        <td colSpan={8} className="empty">
                                            No sales match.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </>
    );
}
