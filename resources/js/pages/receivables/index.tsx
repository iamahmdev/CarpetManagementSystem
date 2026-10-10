import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    PaymentDialog,
    type OpenInvoice,
} from '@/components/ledger/payment-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/ledger';

type CustomerReceivable = {
    id: number;
    name: string;
    phone: string;
    city: string;
    open_invoices: number;
    balance_due: number;
    age_0_30: number;
    age_31_60: number;
    age_61_90: number;
    age_90_plus: number;
    invoices: OpenInvoice[];
};

type Props = {
    customers: CustomerReceivable[];
    metrics: {
        total_outstanding: number;
        customers_with_balance: number;
        oldest_invoice_age: number;
        amount_over_60_days: number;
    };
    filters: {
        location?: string;
        city?: string;
        search?: string;
    };
    locations: { id: number; name: string }[];
    cities: string[];
    can_receive_payment: boolean;
};

export default function ReceivablesIndex(props: Props) {
    const [selectedCustomer, setSelectedCustomer] =
        useState<CustomerReceivable | null>(null);

    function filter(name: 'location' | 'city' | 'search', value: string) {
        router.get(
            '/receivables',
            { ...props.filters, [name]: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Receivables" />
            <main className="ledger-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Receivables</h1>
                        <p className="sub">
                            Open customer balances and invoice ageing.
                        </p>
                    </div>
                </header>
                <div className="ledger-metrics">
                    <section className="card metric">
                        <div className="l">Total outstanding</div>
                        <div className="v">
                            {formatMoney(props.metrics.total_outstanding)}
                        </div>
                    </section>
                    <section className="card metric">
                        <div className="l">Customers with balance</div>
                        <div className="v">
                            {props.metrics.customers_with_balance}
                        </div>
                    </section>
                    <section className="card metric">
                        <div className="l">Oldest invoice age</div>
                        <div className="v">
                            {props.metrics.oldest_invoice_age} days
                        </div>
                    </section>
                    <section className="card metric">
                        <div className="l">Amount over 60 days</div>
                        <div className="v ledger-danger">
                            {formatMoney(props.metrics.amount_over_60_days)}
                        </div>
                    </section>
                </div>
                <section className="card ledger-card" aria-label="Receivables">
                    <div className="ledger-filters">
                        <select
                            aria-label="Filter by location"
                            value={props.filters.location ?? ''}
                            onChange={(event) =>
                                filter('location', event.target.value)
                            }
                        >
                            <option value="">All locations</option>
                            {props.locations.map((location) => (
                                <option key={location.id} value={location.id}>
                                    {location.name}
                                </option>
                            ))}
                        </select>
                        <select
                            aria-label="Filter by city"
                            value={props.filters.city ?? ''}
                            onChange={(event) =>
                                filter('city', event.target.value)
                            }
                        >
                            <option value="">All cities</option>
                            {props.cities.map((city) => (
                                <option key={city}>{city}</option>
                            ))}
                        </select>
                        <Input
                            type="search"
                            aria-label="Search customers"
                            placeholder="Search customer name"
                            value={props.filters.search ?? ''}
                            onChange={(event) =>
                                filter('search', event.target.value)
                            }
                        />
                    </div>
                    <div className="tbl-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                    <th className="r">Open invoices</th>
                                    <th className="r">Balance due</th>
                                    <th className="r">0–30 days</th>
                                    <th className="r">31–60 days</th>
                                    <th className="r">61–90 days</th>
                                    <th className="r">90+ days</th>
                                    {props.can_receive_payment && (
                                        <th>Actions</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {props.customers.map((customer) => (
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
                                            {customer.open_invoices}
                                        </td>
                                        <td className="r b ledger-danger">
                                            {formatMoney(customer.balance_due)}
                                        </td>
                                        <td className="r">
                                            {formatMoney(customer.age_0_30)}
                                        </td>
                                        <td className="r">
                                            {formatMoney(customer.age_31_60)}
                                        </td>
                                        <td className="r">
                                            {formatMoney(customer.age_61_90)}
                                        </td>
                                        <td className="r">
                                            {formatMoney(customer.age_90_plus)}
                                        </td>
                                        {props.can_receive_payment && (
                                            <td>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setSelectedCustomer(
                                                            customer,
                                                        )
                                                    }
                                                >
                                                    Receive payment
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {props.customers.length === 0 && (
                                    <tr>
                                        <td
                                            className="empty"
                                            colSpan={
                                                props.can_receive_payment
                                                    ? 10
                                                    : 9
                                            }
                                        >
                                            No customers have an outstanding
                                            balance.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
            {selectedCustomer && (
                <PaymentDialog
                    open
                    onOpenChange={(open) => {
                        if (!open) setSelectedCustomer(null);
                    }}
                    action={`/customers/${selectedCustomer.id}/payments`}
                    invoices={selectedCustomer.invoices}
                    maxAmount={selectedCustomer.balance_due}
                    allowBackdate={false}
                />
            )}
        </>
    );
}
