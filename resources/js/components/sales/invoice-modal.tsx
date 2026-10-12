import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/ledger';

export interface InvoiceSale {
    id: number;
    invoice_number: string;
    customer: {
        id: number;
        name: string;
        phone: string;
        city: string;
    } | null;
    location: {
        id: number;
        name: string;
        city: string;
    };
    sold_by: string;
    sold_at: string;
    subtotal: number;
    discount: number;
    total: number;
    paid: number;
    balance: number;
    status: string; // 'Paid', 'Partial', 'Unpaid', 'Void'
    items: {
        id?: number;
        piece_id?: number;
        piece_code: string;
        name: string;
        unit_price: number;
    }[];
    payments: {
        id: number;
        amount: number;
        method: string;
        received_at: string;
    }[];
    can_void?: boolean;
}

interface Props {
    isOpen: boolean;
    onClose: () => void;
    sale: InvoiceSale | null;
    canVoid: boolean;
    canCreatePayment: boolean;
    onPaymentRecorded: (message: string, updatedSale: InvoiceSale) => void;
    onSaleVoided: (message: string) => void;
}

export function InvoiceModal({
    isOpen,
    onClose,
    sale,
    canVoid,
    canCreatePayment,
    onPaymentRecorded,
    onSaleVoided,
}: Props) {
    if (!sale) {
        return null;
    }

    const [payAmount, setPayAmount] = useState<string>(String(sale.balance));
    const [payMethod, setPayMethod] = useState<string>('cash');
    const [payError, setPayError] = useState<string>('');
    const [isSavingPayment, setIsSavingPayment] = useState(false);
    const [isVoiding, setIsVoiding] = useState(false);

    function formatDisplayDate(isoString: string): string {
        try {
            const date = new Date(isoString);
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            const hh = String(date.getHours()).padStart(2, '0');
            const min = String(date.getMinutes()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd} ${hh}:${min}`;
        } catch {
            return isoString;
        }
    }

    function formatPaymentDate(isoString: string): string {
        try {
            const date = new Date(isoString);
            const yyyy = date.getFullYear();
            const mm = String(date.getMonth() + 1).padStart(2, '0');
            const dd = String(date.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        } catch {
            return isoString;
        }
    }

    async function handleRecordPayment(e: React.FormEvent) {
        e.preventDefault();
        setPayError('');
        const trimmed = payAmount.trim();
        const num = Number(trimmed);

        if (!trimmed || isNaN(num) || num <= 0) {
            setPayError('Enter an amount');
            return;
        }

        if (num > sale.balance) {
            setPayError('The amount cannot exceed the balance.');
            return;
        }

        setIsSavingPayment(true);
        try {
            const response = await fetch(`/sales/${sale.id}/payments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    amount: num,
                    method: payMethod,
                }),
            });

            const data = await response.json();
            if (!response.ok) {
                setPayError(data.message || 'Error recording payment.');
                return;
            }

            if (data.sale) {
                onPaymentRecorded(data.message || `Payment of ${formatMoney(num)} recorded`, data.sale);
                setPayAmount(String(data.sale.balance));
            }
        } catch {
            setPayError('Unable to record payment.');
        } finally {
            setIsSavingPayment(false);
        }
    }

    async function handleVoidSale() {
        if (!confirm(`Void ${sale.invoice_number}? The pieces go back into stock.`)) {
            return;
        }

        setIsVoiding(true);
        try {
            const response = await fetch(`/sales/${sale.id}/void`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
            });

            const data = await response.json();
            if (!response.ok) {
                alert(data.message || 'Unable to void sale.');
                return;
            }

            onSaleVoided(data.message || `${sale.invoice_number} voided, pieces back in stock`);
            onClose();
        } catch {
            alert('Unable to void sale.');
        } finally {
            setIsVoiding(false);
        }
    }

    // Badge logic: "Void" (red) if voided; otherwise "Paid" (green) if paid >= total, "Partial" (amber) if paid is above 0, "Unpaid" (red)
    let badgeText = 'Unpaid';
    let badgeColorClass = 'bg-red-100 text-red-800 border-red-300';

    if (sale.status.toLowerCase() === 'void') {
        badgeText = 'Void';
        badgeColorClass = 'bg-red-100 text-red-800 border-red-300';
    } else if (sale.paid >= sale.total) {
        badgeText = 'Paid';
        badgeColorClass = 'bg-emerald-100 text-emerald-800 border-emerald-300';
    } else if (sale.paid > 0) {
        badgeText = 'Partial';
        badgeColorClass = 'bg-amber-100 text-amber-800 border-amber-300';
    }

    return (
        <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-w-2xl p-6 print:p-0 print:border-none print:shadow-none print:max-w-none text-slate-900 bg-white">
                <DialogHeader className="sr-only">
                    <DialogTitle>{sale.invoice_number}</DialogTitle>
                </DialogHeader>

                <div className="printable-invoice space-y-6">
                    {/* Header: Top left & Top right */}
                    <div className="flex items-start justify-between border-b pb-4">
                        <div>
                            <h2 className="text-xl font-bold text-slate-900">Carpet system</h2>
                            <p className="text-sm text-slate-600">
                                {sale.location.name}, {sale.location.city}
                            </p>
                            <p className="text-xs text-slate-400 mt-1">{sale.invoice_number}</p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm text-slate-600 font-medium">
                                {formatDisplayDate(sale.sold_at)}
                            </p>
                            <div className="mt-1">
                                <span
                                    className={`inline-block px-2.5 py-0.5 text-xs font-semibold rounded-full border ${badgeColorClass}`}
                                >
                                    {badgeText}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Customer & Seller info */}
                    <div className="grid grid-cols-2 gap-4 text-sm bg-slate-50 p-3 rounded-md border border-slate-200">
                        <div>
                            <span className="text-slate-500 font-medium">Customer: </span>
                            <span className="font-semibold text-slate-900">
                                {sale.customer
                                    ? `${sale.customer.name}${
                                          sale.customer.phone && sale.customer.phone !== '-'
                                              ? ` (${sale.customer.phone})`
                                              : ''
                                      }`
                                    : 'Walk-in customer'}
                            </span>
                        </div>
                        <div className="text-right">
                            <span className="text-slate-500 font-medium">Sold by: </span>
                            <span className="font-semibold text-slate-900">{sale.sold_by}</span>
                        </div>
                    </div>

                    {/* Items Table */}
                    <div className="border border-slate-200 rounded-md overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-100 text-slate-700 font-semibold border-b border-slate-200">
                                <tr>
                                    <th className="text-left py-2 px-3">Piece</th>
                                    <th className="text-left py-2 px-3">Carpet</th>
                                    <th className="text-right py-2 px-3">Price</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200">
                                {sale.items.map((item, index) => (
                                    <tr key={index} className="hover:bg-slate-50">
                                        <td className="py-2.5 px-3 font-bold text-slate-900 font-mono">
                                            {item.piece_code}
                                        </td>
                                        <td className="py-2.5 px-3 text-slate-700">{item.name}</td>
                                        <td className="py-2.5 px-3 text-right font-medium text-slate-900">
                                            {formatMoney(item.unit_price)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {/* Totals */}
                    <div className="flex justify-end">
                        <div className="w-64 space-y-1.5 text-sm">
                            <div className="flex justify-between text-slate-600">
                                <span>Subtotal</span>
                                <span>{formatMoney(sale.subtotal)}</span>
                            </div>
                            {sale.discount > 0 && (
                                <div className="flex justify-between text-slate-600">
                                    <span>Discount</span>
                                    <span>{formatMoney(sale.discount)}</span>
                                </div>
                            )}
                            <div className="flex justify-between font-bold text-base text-slate-900 border-t pt-1.5">
                                <span>Total</span>
                                <span>{formatMoney(sale.total)}</span>
                            </div>
                            <div className="flex justify-between text-slate-600">
                                <span>Paid</span>
                                <span>{formatMoney(sale.paid)}</span>
                            </div>
                            <div className="flex justify-between font-bold text-slate-900 border-t pt-1.5">
                                <span>Balance due</span>
                                <span className={sale.balance > 0 ? 'text-red-600 font-bold' : ''}>
                                    {formatMoney(sale.balance)}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Payments line if payments exist */}
                    {sale.payments && sale.payments.length > 0 && (
                        <div className="text-xs text-slate-600 bg-slate-50 p-2.5 rounded border border-slate-200">
                            <strong>Payments: </strong>
                            {sale.payments
                                .map(
                                    (p) =>
                                        `${formatMoney(p.amount)} (${p.method.replace(/_/g, ' ')}, ${formatPaymentDate(
                                            p.received_at,
                                        )})`,
                                )
                                .join('; ')}
                        </div>
                    )}

                    {/* Record a payment section (only if Balance due > 0 and user has sales.create, NOT PRINTED) */}
                    {sale.balance > 0 && canCreatePayment && sale.status.toLowerCase() !== 'void' && (
                        <div className="print:hidden border border-slate-200 rounded-md p-4 bg-slate-50 space-y-3">
                            <h4 className="text-sm font-semibold text-slate-900">Record a payment</h4>
                            <form onSubmit={handleRecordPayment} className="space-y-3">
                                <div className="grid grid-cols-2 gap-3">
                                    <div>
                                        <Label htmlFor="record-payment-amount" className="text-xs text-slate-600">
                                            Amount (PKR)
                                        </Label>
                                        <Input
                                            id="record-payment-amount"
                                            type="number"
                                            min="1"
                                            max={sale.balance}
                                            value={payAmount}
                                            onChange={(e) => setPayAmount(e.target.value)}
                                            className="mt-1 bg-white h-9 text-sm"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="record-payment-method" className="text-xs text-slate-600">
                                            Payment method
                                        </Label>
                                        <select
                                            id="record-payment-method"
                                            value={payMethod}
                                            onChange={(e) => setPayMethod(e.target.value)}
                                            className="mt-1 w-full h-9 rounded-md border border-slate-300 bg-white px-3 text-sm"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="bank_transfer">Bank transfer</option>
                                            <option value="cheque">Cheque</option>
                                            <option value="mobile_wallet">Mobile wallet</option>
                                        </select>
                                    </div>
                                </div>
                                {payError && <p className="text-xs text-red-600 font-medium">{payError}</p>}
                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={isSavingPayment}
                                        className="bg-slate-900 hover:bg-slate-800 text-white"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}

                    {/* Buttons: Void sale (red, owner/admin and completed), Print, Close (NOT PRINTED) */}
                    <div className="print:hidden flex items-center justify-between border-t pt-4">
                        <div>
                            {canVoid && sale.status.toLowerCase() !== 'void' && (
                                <Button
                                    type="button"
                                    variant="destructive"
                                    size="sm"
                                    disabled={isVoiding}
                                    onClick={handleVoidSale}
                                    className="bg-red-600 hover:bg-red-700 text-white"
                                >
                                    Void sale
                                </Button>
                            )}
                        </div>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => window.print()}
                            >
                                Print
                            </Button>
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                onClick={onClose}
                            >
                                Close
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
