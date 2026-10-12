import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { formatMoney } from '@/lib/ledger';
import { InvoiceModal, type InvoiceSale } from '@/components/sales/invoice-modal';

type SaleListItem = InvoiceSale & {
    customer_name: string;
    has_customer: boolean;
    piece_codes: string[];
};

type Props = {
    sales: SaleListItem[];
    is_sales_role: boolean;
    can_void: boolean;
    can_create_payment: boolean;
};

export default function SalesIndex({
    sales: initialSales,
    is_sales_role,
    can_void,
    can_create_payment,
}: Props) {
    const [salesList, setSalesList] = useState<SaleListItem[]>(initialSales);

    // Keep salesList updated if props change
    useEffect(() => {
        setSalesList(initialSales);
    }, [initialSales]);

    // Search remembered while visiting other pages
    const [search, setSearch] = useState<string>(() => {
        if (typeof window !== 'undefined') {
            return sessionStorage.getItem('sales_history_search') || '';
        }
        return '';
    });

    useEffect(() => {
        if (typeof window !== 'undefined') {
            sessionStorage.setItem('sales_history_search', search);
        }
    }, [search]);

    // Selected sale for the invoice window modal
    const [selectedSale, setSelectedSale] = useState<SaleListItem | null>(null);
    const [isInvoiceOpen, setIsInvoiceOpen] = useState(false);

    // Notification toast
    const [notification, setNotification] = useState<{
        message: string;
        type: 'success' | 'error';
    } | null>(null);

    function showNotification(message: string, type: 'success' | 'error' = 'success') {
        setNotification({ message, type });
        setTimeout(() => {
            setNotification((prev) => (prev?.message === message ? null : prev));
        }, 5000);
    }

    // Format day and short month (e.g. "8 Oct", "30 Sept", "12 Oct")
    function formatSaleDate(isoString: string): string {
        try {
            const d = new Date(isoString);
            const day = d.getDate();
            const months = [
                'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sept', 'Oct', 'Nov', 'Dec',
            ];
            return `${day} ${months[d.getMonth()]}`;
        } catch {
            return isoString;
        }
    }

    // Real-time client-side search
    // Matches invoice number, customer name ("walk-in" for no customer), and codes of any piece on the invoice
    const filteredSales = useMemo(() => {
        const q = search.trim().toLowerCase();
        if (!q) {
            return salesList;
        }

        return salesList.filter((sale) => {
            const invoiceMatch = sale.invoice_number.toLowerCase().includes(q);
            const customerText = sale.has_customer
                ? sale.customer_name.toLowerCase()
                : 'walk-in';
            const customerMatch = customerText.includes(q);
            const pieceMatch = (sale.piece_codes || []).some((code) =>
                code.toLowerCase().includes(q),
            );

            return invoiceMatch || customerMatch || pieceMatch;
        });
    }, [salesList, search]);

    // Payment badge helper
    function renderPaymentBadge(sale: SaleListItem) {
        if (sale.status.toLowerCase() === 'void') {
            return (
                <span className="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-[#fce8e6] text-[#c5221f]">
                    Void
                </span>
            );
        }

        if (sale.paid >= sale.total) {
            return (
                <span className="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-[#e6f4ea] text-[#137333]">
                    Paid
                </span>
            );
        }

        if (sale.paid > 0) {
            return (
                <span className="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-[#fef7e0] text-[#b06000]">
                    Partial
                </span>
            );
        }

        return (
            <span className="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-[#fce8e6] text-[#c5221f]">
                Unpaid
            </span>
        );
    }

    function handleRowClick(sale: SaleListItem) {
        setSelectedSale(sale);
        setIsInvoiceOpen(true);
    }

    function handlePaymentRecorded(message: string, updatedSale: InvoiceSale) {
        setSalesList((prev) =>
            prev.map((item) =>
                item.id === updatedSale.id
                    ? {
                          ...item,
                          paid: updatedSale.paid,
                          balance: updatedSale.balance,
                          status: updatedSale.status,
                          payments: updatedSale.payments,
                      }
                    : item,
            ),
        );
        setSelectedSale((prev) => (prev ? { ...prev, ...updatedSale } : null));
        showNotification(message, 'success');
    }

    function handleSaleVoided(message: string) {
        if (selectedSale) {
            setSalesList((prev) =>
                prev.map((item) =>
                    item.id === selectedSale.id
                        ? {
                              ...item,
                              status: 'Void',
                              balance: 0,
                          }
                        : item,
                ),
            );
        }
        setIsInvoiceOpen(false);
        showNotification(message, 'success');
    }

    return (
        <>
            <Head title="Sales history" />

            <div className="flex flex-1 flex-col w-full p-6 md:p-8 space-y-6">
                {/* Page Header (No header buttons) */}
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
                        Sales history
                    </h1>
                    <p className="text-sm text-slate-500 mt-1">
                        {is_sales_role
                            ? 'Your own sales.'
                            : 'All invoices across the displays.'}
                    </p>
                </div>

                    {/* Notification message banner */}
                    {notification && (
                        <div
                            className={`p-3.5 rounded-lg border text-sm font-medium transition-all ${
                                notification.type === 'error'
                                    ? 'bg-red-50 text-red-800 border-red-200'
                                    : 'bg-emerald-50 text-emerald-800 border-emerald-200'
                            }`}
                        >
                            {notification.message}
                        </div>
                    )}

                    {/* Single card containing search box and table */}
                    <div className="w-full bg-white rounded-xl border border-slate-200/80 p-6 shadow-xs space-y-6">
                        {/* Search Input (max-width 320px) */}
                        <div className="max-w-[320px]">
                            <Input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Search invoice, customer, or piece ID"
                                className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
                            />
                        </div>

                        {/* Table */}
                        <div className="w-full overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs font-semibold text-slate-500">
                                        <th className="py-3 px-4 font-semibold">Invoice</th>
                                        <th className="py-3 px-4 font-semibold">Date</th>
                                        <th className="py-3 px-4 font-semibold">Location</th>
                                        <th className="py-3 px-4 font-semibold">Customer</th>
                                        <th className="py-3 px-4 font-semibold">Sold by</th>
                                        <th className="py-3 px-4 text-right font-semibold">Total</th>
                                        <th className="py-3 px-4 font-semibold">Payment</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {filteredSales.map((sale) => (
                                        <tr
                                            key={sale.id}
                                            onClick={() => handleRowClick(sale)}
                                            className="hover:bg-slate-50/70 cursor-pointer transition-colors"
                                        >
                                            <td className="py-3.5 px-4 font-bold text-slate-900">
                                                {sale.invoice_number}
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-700 whitespace-nowrap">
                                                {formatSaleDate(sale.sold_at)}
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-700 whitespace-nowrap">
                                                {sale.location.name}
                                            </td>
                                            <td className="py-3.5 px-4 whitespace-nowrap">
                                                {sale.has_customer ? (
                                                    <span className="text-slate-900 font-normal">
                                                        {sale.customer_name}
                                                    </span>
                                                ) : (
                                                    <span className="text-slate-400 font-normal">
                                                        Walk-in
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-3.5 px-4 text-slate-700 whitespace-nowrap">
                                                {sale.sold_by}
                                            </td>
                                            <td className="py-3.5 px-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                                {formatMoney(sale.total)}
                                            </td>
                                            <td className="py-3.5 px-4 whitespace-nowrap">
                                                {renderPaymentBadge(sale)}
                                            </td>
                                        </tr>
                                    ))}

                                    {filteredSales.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="py-12 text-center text-sm text-slate-500"
                                            >
                                                No sales match.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Invoice Window Modal */}
            <InvoiceModal
                isOpen={isInvoiceOpen}
                onClose={() => setIsInvoiceOpen(false)}
                sale={selectedSale}
                canVoid={can_void}
                canCreatePayment={can_create_payment}
                onPaymentRecorded={handlePaymentRecorded}
                onSaleVoided={handleSaleVoided}
            />
        </>
    );
}
