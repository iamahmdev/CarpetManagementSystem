import { Head } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
import {
    type CartItem,
    type SaleCart,
    clearCartKeepDisplay,
    loadCart,
    saveCart,
} from '@/lib/sale-cart';
import { InvoiceModal, type InvoiceSale } from '@/components/sales/invoice-modal';

type Props = {
    customers: { id: number; name: string; phone: string; city: string }[];
    locations: { id: number; name: string; city: string }[];
    pieces: {
        id: number;
        code: string;
        sku: string;
        carpet: string;
        size: string;
        design: string;
        location_id: number;
        location: string;
        status: string;
        price: number;
    }[];
    payment_methods: { value: string; label: string }[];
    selected_customer_id: number | null;
    selected_piece_id: number | null;
    can_void: boolean;
    user_name: string;
};

export default function SalesCreate({
    customers: initialCustomers,
    locations,
    pieces,
    selected_customer_id,
    selected_piece_id,
    can_void,
    user_name,
}: Props) {
    // Determine default display (default: Hangu Display)
    const defaultLocation = useMemo(() => {
        return (
            locations.find((l) => l.name === 'Hangu Display') ||
            locations[0] ||
            null
        );
    }, [locations]);

    // Customers list state (can be updated when adding a customer)
    const [customersList, setCustomersList] = useState(initialCustomers);

    // Initial cart loading from persistent memory (localStorage)
    const [cart, setCart] = useState<SaleCart>(() => {
        const stored = loadCart(defaultLocation?.id ?? null);
        // Verify stored location exists in active displays
        const isValidLocation = locations.some((l) => l.id === stored.location_id);
        const activeLocationId = isValidLocation ? stored.location_id : defaultLocation?.id ?? null;

        // If piece_id was passed via query param or selected_piece_id
        if (selected_piece_id) {
            const initialPiece = pieces.find((p) => p.id === selected_piece_id);
            if (initialPiece) {
                return {
                    location_id: initialPiece.location_id,
                    items: [
                        {
                            piece_id: initialPiece.id,
                            code: initialPiece.code,
                            name: `${initialPiece.carpet} ${initialPiece.size}`,
                            price: initialPiece.price,
                        },
                    ],
                    discount: 0,
                    customer_id: selected_customer_id ? String(selected_customer_id) : '',
                    payment_method: 'cash',
                    paid_now: null,
                    filter_text: '',
                };
            }
        }

        return {
            ...stored,
            location_id: activeLocationId,
            customer_id: selected_customer_id ? String(selected_customer_id) : stored.customer_id,
        };
    });

    // Save cart whenever it changes
    useEffect(() => {
        saveCart(cart);
    }, [cart]);

    // Scan / Piece ID input state
    const [pieceCodeInput, setPieceCodeInput] = useState('');
    const [pieceCodeError, setPieceCodeError] = useState('');
    const idInputRef = useRef<HTMLInputElement>(null);

    // Notification toast / message banner
    const [notification, setNotification] = useState<{
        message: string;
        type: 'success' | 'error';
    } | null>(null);

    // Add Customer Modal state
    const [isCustomerModalOpen, setIsCustomerModalOpen] = useState(false);
    const [custName, setCustName] = useState('');
    const [custPhone, setCustPhone] = useState('');
    const [custCity, setCustCity] = useState('');
    const [custError, setCustError] = useState('');
    const [isSavingCustomer, setIsSavingCustomer] = useState(false);

    // Invoice Window Modal state
    const [invoiceModalSale, setInvoiceModalSale] = useState<InvoiceSale | null>(null);
    const [isInvoiceModalOpen, setIsInvoiceModalOpen] = useState(false);
    const [isSubmittingSale, setIsSubmittingSale] = useState(false);

    // Calculations
    const subtotal = useMemo(() => {
        return cart.items.reduce((sum, item) => sum + (Number(item.price) || 0), 0);
    }, [cart.items]);

    const discount = useMemo(() => {
        return Math.min(Math.max(0, Number(cart.discount) || 0), subtotal);
    }, [cart.discount, subtotal]);

    const total = useMemo(() => {
        return Math.max(0, subtotal - discount);
    }, [subtotal, discount]);

    const paidNow = useMemo(() => {
        if (cart.paid_now === null) {
            return total;
        }
        return Math.min(Math.max(0, Number(cart.paid_now) || 0), total);
    }, [cart.paid_now, total]);

    const balanceDue = useMemo(() => {
        return Math.max(0, total - paidNow);
    }, [total, paidNow]);

    // Available pieces list at selected display (status: in_stock, not on invoice)
    const availablePiecesAtDisplay = useMemo(() => {
        if (!cart.location_id) return [];
        const invoicePieceIds = new Set(cart.items.map((i) => i.piece_id));
        return pieces.filter(
            (p) =>
                p.location_id === cart.location_id &&
                p.status === 'in_stock' &&
                !invoicePieceIds.has(p.id),
        );
    }, [pieces, cart.location_id, cart.items]);

    // Filter available pieces (case-insensitive across piece code, carpet name and size, and design)
    const filteredAvailablePieces = useMemo(() => {
        const filter = (cart.filter_text || '').trim().toLowerCase();
        if (!filter) return availablePiecesAtDisplay;

        return availablePiecesAtDisplay.filter((p) => {
            const codeMatch = p.code.toLowerCase().includes(filter);
            const nameSizeMatch = `${p.carpet} ${p.size}`.toLowerCase().includes(filter);
            const designMatch = (p.design || '').toLowerCase().includes(filter);
            return codeMatch || nameSizeMatch || designMatch;
        });
    }, [availablePiecesAtDisplay, cart.filter_text]);

    // Top 40 rows
    const visibleAvailablePieces = useMemo(() => {
        return filteredAvailablePieces.slice(0, 40);
    }, [filteredAvailablePieces]);

    const remainingAvailableCount = Math.max(
        0,
        filteredAvailablePieces.length - visibleAvailablePieces.length,
    );

    // Show temporary notification
    function showNotification(message: string, type: 'success' | 'error' = 'success') {
        setNotification({ message, type });
        setTimeout(() => {
            setNotification((prev) => (prev?.message === message ? null : prev));
        }, 5000);
    }

    // 1. Changing Selling Display
    function handleDisplayChange(newLocationId: number) {
        // Changing it resets the whole cart (pieces, prices, discount, customer, method, paid, filter) and sets the new display.
        const reset = clearCartKeepDisplay(newLocationId);
        setCart(reset);
        setPieceCodeInput('');
        setPieceCodeError('');
    }

    // 2. Scan or type piece ID
    async function handleAddPieceById(e?: React.FormEvent) {
        if (e) {
            e.preventDefault();
        }
        setPieceCodeError('');
        const trimmed = pieceCodeInput.trim().toUpperCase();
        if (!trimmed) {
            return;
        }

        // If input is only digits, it becomes "CP-" + number padded to 6 digits (3617 becomes CP-003617)
        let normalizedCode = trimmed;
        if (/^\d+$/.test(trimmed)) {
            normalizedCode = `CP-${trimmed.padStart(6, '0')}`;
        } else if (/^CP-\d+$/.test(trimmed)) {
            const numPart = trimmed.replace('CP-', '');
            normalizedCode = `CP-${numPart.padStart(6, '0')}`;
        }

        // Call server lookup for comprehensive status and location checks
        try {
            const params = new URLSearchParams({
                code: normalizedCode,
                location_id: String(cart.location_id || ''),
            });
            cart.items.forEach((item) => {
                params.append('existing_piece_ids[]', String(item.piece_id));
            });

            const res = await fetch(`/pieces/lookup?${params.toString()}`, {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();

            if (!res.ok) {
                setPieceCodeError(data.message || 'Error finding piece');
                return;
            }

            if (data.piece) {
                const newPiece: CartItem = {
                    piece_id: data.piece.id,
                    code: data.piece.code,
                    name: data.piece.name,
                    price: data.piece.price,
                };

                // Adding a piece does not reset a manually typed "Amount paid now"
                setCart((prev) => ({
                    ...prev,
                    items: [...prev.items, newPiece],
                }));
                setPieceCodeInput('');
                setPieceCodeError('');
                idInputRef.current?.focus();
            }
        } catch {
            setPieceCodeError('Unable to look up piece.');
        }
    }

    // Add piece from available list
    function handleAddPieceFromList(piece: Props['pieces'][number]) {
        // Adding a piece does not reset a manually typed "Amount paid now"
        const newPiece: CartItem = {
            piece_id: piece.id,
            code: piece.code,
            name: `${piece.carpet} ${piece.size}`,
            price: piece.price,
        };
        setCart((prev) => ({
            ...prev,
            items: [...prev.items, newPiece],
        }));
        setPieceCodeError('');
    }

    // Remove piece from invoice
    function handleRemovePiece(index: number) {
        // Removing a piece does not reset a manually typed "Amount paid now"
        setCart((prev) => ({
            ...prev,
            items: prev.items.filter((_, i) => i !== index),
        }));
    }

    // Edit price in invoice
    function handlePriceChange(index: number, newPriceStr: string) {
        const val = Math.max(0, Number(newPriceStr) || 0);
        // Editing a price: value cannot go below 0, and the "Amount paid now" goes back to automatic
        setCart((prev) => ({
            ...prev,
            paid_now: null, // resets to automatic
            items: prev.items.map((item, i) =>
                i === index ? { ...item, price: val } : item,
            ),
        }));
    }

    // Change discount
    function handleDiscountChange(newDiscountStr: string) {
        const val = Math.max(0, Number(newDiscountStr) || 0);
        // Changing it sets "Amount paid now" back to automatic
        setCart((prev) => ({
            ...prev,
            discount: val,
            paid_now: null, // resets to automatic
        }));
    }

    // Change Paid Now
    function handlePaidNowChange(valStr: string) {
        if (valStr === '') {
            setCart((prev) => ({
                ...prev,
                paid_now: null, // automatic
            }));
            return;
        }
        const val = Math.max(0, Number(valStr) || 0);
        setCart((prev) => ({
            ...prev,
            paid_now: val,
        }));
    }

    // Save New Customer
    async function handleSaveCustomer(e: React.FormEvent) {
        e.preventDefault();
        setCustError('');
        const trimmedName = custName.trim();
        if (!trimmedName) {
            setCustError('Enter a name');
            return;
        }

        setIsSavingCustomer(true);
        try {
            const res = await fetch('/customers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify({
                    name: trimmedName,
                    phone: custPhone.trim() || '-',
                    city: custCity.trim() || '-',
                }),
            });

            const data = await res.json();
            if (!res.ok) {
                setCustError(data.message || 'Unable to save customer.');
                return;
            }

            const newCust = data.customer;
            setCustomersList((prev) => {
                const exists = prev.some((c) => c.id === newCust.id);
                return exists ? prev : [...prev, newCust].sort((a, b) => a.name.localeCompare(b.name));
            });

            // Automatically select on invoice
            setCart((prev) => ({
                ...prev,
                customer_id: String(newCust.id),
            }));

            setIsCustomerModalOpen(false);
            setCustName('');
            setCustPhone('');
            setCustCity('');
            showNotification(`${newCust.name} added`, 'success');
        } catch {
            setCustError('Unable to save customer.');
        } finally {
            setIsSavingCustomer(false);
        }
    }

    // Complete Sale
    async function handleCompleteSale() {
        setNotification(null);

        // Validation 1: No pieces
        if (cart.items.length === 0) {
            showNotification('Add at least one piece', 'error');
            return;
        }

        // Validation 2: Balance due > 0 and no customer selected
        if (balanceDue > 0 && !cart.customer_id) {
            showNotification('Choose a customer to record a balance due', 'error');
            return;
        }

        setIsSubmittingSale(true);
        try {
            const payload = {
                location_id: cart.location_id,
                customer_id: cart.customer_id ? Number(cart.customer_id) : null,
                discount: discount,
                paid_now: paidNow,
                method: cart.payment_method,
                items: cart.items.map((i) => ({
                    piece_id: i.piece_id,
                    unit_price: i.price,
                })),
            };

            const res = await fetch('/sales', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN':
                        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '',
                },
                body: JSON.stringify(payload),
            });

            const data = await res.json();
            if (!res.ok) {
                showNotification(data.message || 'Unable to complete sale.', 'error');
                return;
            }

            // Success: clear cart keeping display
            clearCartKeepDisplay(cart.location_id);
            setCart((prev) => ({
                ...prev,
                items: [],
                discount: 0,
                customer_id: '',
                payment_method: 'cash',
                paid_now: null,
                filter_text: '',
            }));

            // Open Invoice Window
            setInvoiceModalSale(data.sale);
            setIsInvoiceModalOpen(true);
            showNotification(data.message || `Sale ${data.sale?.invoice_number} completed`, 'success');
        } catch {
            showNotification('Unable to complete sale. Please try again.', 'error');
        } finally {
            setIsSubmittingSale(false);
        }
    }

    return (
        <>
            <Head title="New sale" />

            <div className="min-h-screen bg-slate-50 text-slate-900 p-6">
                <div className="max-w-7xl mx-auto space-y-6">
                    {/* Header */}
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900">New sale</h1>
                        <p className="text-sm text-slate-500 mt-1">
                            Pick the display, add pieces by ID, take payment, and print the invoice.
                        </p>
                    </div>

                    {/* Toast / Notification Banner */}
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

                    {/* Two cards side by side */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        {/* LEFT CARD: Pieces */}
                        <div className="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-5">
                            <h2 className="text-lg font-bold text-slate-900">Pieces</h2>

                            {/* 1. Selling from dropdown */}
                            <div>
                                <Label htmlFor="selling-display" className="text-sm font-medium text-slate-600 block mb-1.5">
                                    Selling from
                                </Label>
                                <select
                                    id="selling-display"
                                    value={cart.location_id ?? ''}
                                    onChange={(e) => handleDisplayChange(Number(e.target.value))}
                                    className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-400"
                                >
                                    {locations.map((loc) => (
                                        <option key={loc.id} value={loc.id}>
                                            {loc.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* 2. Scan or type a piece ID */}
                            <div>
                                <Label htmlFor="scan-piece-id" className="text-sm font-medium text-slate-600 block mb-1.5">
                                    Scan or type a piece ID
                                </Label>
                                <form onSubmit={handleAddPieceById} className="flex gap-2">
                                    <Input
                                        id="scan-piece-id"
                                        ref={idInputRef}
                                        type="text"
                                        value={pieceCodeInput}
                                        onChange={(e) => setPieceCodeInput(e.target.value)}
                                        placeholder="CP-003617 or 3617"
                                        className="flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm uppercase placeholder:normal-case placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
                                    />
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        className="px-5 border border-slate-200 font-semibold"
                                    >
                                        Add
                                    </Button>
                                </form>
                                {pieceCodeError && (
                                    <p className="text-xs text-red-600 font-medium mt-1.5">{pieceCodeError}</p>
                                )}
                            </div>

                            {/* 3. Filter available pieces */}
                            <div>
                                <Input
                                    type="text"
                                    value={cart.filter_text}
                                    onChange={(e) =>
                                        setCart((prev) => ({
                                            ...prev,
                                            filter_text: e.target.value,
                                        }))
                                    }
                                    placeholder="Filter available pieces"
                                    className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-slate-400"
                                />
                            </div>

                            {/* 4. Available pieces list */}
                            <div className="border border-slate-200 rounded-lg overflow-hidden">
                                <div className="max-h-[360px] overflow-y-auto divide-y divide-slate-100">
                                    {visibleAvailablePieces.length === 0 ? (
                                        <div className="py-8 text-center text-sm text-slate-400">
                                            No available pieces here.
                                        </div>
                                    ) : (
                                        visibleAvailablePieces.map((piece) => (
                                            <div
                                                key={piece.id}
                                                className="p-3 flex items-center justify-between gap-3 hover:bg-slate-50/80 transition-colors"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-baseline gap-2">
                                                        <span className="font-bold text-slate-900 font-mono text-sm">
                                                            {piece.code}
                                                        </span>
                                                        <span className="text-sm text-slate-700 truncate">
                                                            {piece.carpet} {piece.size}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-3">
                                                    <span className="text-sm font-medium text-slate-900 whitespace-nowrap">
                                                        {formatMoney(piece.price)}
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleAddPieceFromList(piece)}
                                                        className="h-8 px-3 border-slate-200 font-medium"
                                                    >
                                                        Add
                                                    </Button>
                                                </div>
                                            </div>
                                        ))
                                    )}
                                </div>
                                {remainingAvailableCount > 0 && (
                                    <div className="p-2.5 bg-slate-50 border-t border-slate-200 text-xs text-center text-slate-500 font-medium">
                                        {remainingAvailableCount} more. Use the filter above.
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* RIGHT CARD: Invoice */}
                        <div className="bg-white rounded-xl border border-slate-200/80 p-6 shadow-sm space-y-5">
                            <h2 className="text-lg font-bold text-slate-900">Invoice</h2>

                            {/* 1. Invoice pieces list (max height 220px) */}
                            <div className="border border-slate-200 rounded-lg overflow-hidden min-h-[140px] max-h-[220px] overflow-y-auto">
                                {cart.items.length === 0 ? (
                                    <div className="py-12 px-4 text-center text-sm text-slate-400">
                                        Add pieces from the list, or type an ID.
                                    </div>
                                ) : (
                                    <div className="divide-y divide-slate-100">
                                        {cart.items.map((item, index) => (
                                            <div
                                                key={`${item.piece_id}-${index}`}
                                                className="p-3 flex items-center justify-between gap-3 hover:bg-slate-50/50"
                                            >
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-bold text-slate-900 font-mono text-sm">
                                                        {item.code}
                                                    </div>
                                                    <div className="text-xs text-slate-500 truncate">
                                                        {item.name}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="500"
                                                        value={item.price}
                                                        onChange={(e) =>
                                                            handlePriceChange(index, e.target.value)
                                                        }
                                                        className="w-28 h-8 text-right text-sm font-medium border-slate-200"
                                                    />
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleRemovePiece(index)}
                                                        className="h-8 w-8 p-0 text-slate-400 hover:text-red-600"
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>

                            {/* 2. Customer row */}
                            <div>
                                <Label htmlFor="sale-customer" className="text-sm font-medium text-slate-600 block mb-1.5">
                                    Customer
                                </Label>
                                <div className="flex gap-2">
                                    <select
                                        id="sale-customer"
                                        value={cart.customer_id}
                                        onChange={(e) =>
                                            setCart((prev) => ({
                                                ...prev,
                                                customer_id: e.target.value,
                                            }))
                                        }
                                        className="flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-400"
                                    >
                                        <option value="">Walk-in customer</option>
                                        {customersList.map((cust) => (
                                            <option key={cust.id} value={cust.id}>
                                                {cust.name}
                                            </option>
                                        ))}
                                    </select>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setIsCustomerModalOpen(true)}
                                        className="border border-slate-200 font-medium text-sm flex items-center gap-1.5 px-3"
                                    >
                                        <Plus className="h-4 w-4" /> New customer
                                    </Button>
                                </div>
                            </div>

                            {/* 3. Discount and payment method row */}
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="sale-discount" className="text-sm font-medium text-slate-600 block mb-1.5">
                                        Discount (PKR)
                                    </Label>
                                    <Input
                                        id="sale-discount"
                                        type="number"
                                        min="0"
                                        step="500"
                                        placeholder="0"
                                        value={cart.discount || ''}
                                        onChange={(e) => handleDiscountChange(e.target.value)}
                                        className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="payment-method" className="text-sm font-medium text-slate-600 block mb-1.5">
                                        Payment method
                                    </Label>
                                    <select
                                        id="payment-method"
                                        value={cart.payment_method}
                                        onChange={(e) =>
                                            setCart((prev) => ({
                                                ...prev,
                                                payment_method: e.target.value,
                                            }))
                                        }
                                        className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-slate-400"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank transfer</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="mobile_wallet">Mobile wallet</option>
                                    </select>
                                </div>
                            </div>

                            {/* 4. Amount paid now (PKR) */}
                            <div>
                                <Label htmlFor="amount-paid-now" className="text-sm font-medium text-slate-600 block mb-1.5">
                                    Amount paid now (PKR)
                                </Label>
                                <Input
                                    id="amount-paid-now"
                                    type="number"
                                    min="0"
                                    step="500"
                                    value={cart.paid_now !== null ? cart.paid_now : total}
                                    onChange={(e) => handlePaidNowChange(e.target.value)}
                                    className="w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                                />
                            </div>

                            {/* 5. Totals */}
                            <div className="space-y-1.5 text-sm pt-2">
                                <div className="flex justify-between text-slate-600">
                                    <span>Subtotal</span>
                                    <span>{formatMoney(subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>Discount</span>
                                    <span>{formatMoney(discount)}</span>
                                </div>
                                <div className="flex justify-between font-bold text-lg text-slate-900 border-t border-slate-100 pt-2">
                                    <span>Total</span>
                                    <span>{formatMoney(total)}</span>
                                </div>
                                <div className="flex justify-between text-slate-600">
                                    <span>Paid now</span>
                                    <span>{formatMoney(paidNow)}</span>
                                </div>
                                <div className="flex justify-between font-medium text-slate-900 border-t border-slate-100 pt-1.5">
                                    <span>Balance due</span>
                                    <span className={balanceDue > 0 ? 'text-red-600 font-bold' : ''}>
                                        {formatMoney(balanceDue)}
                                    </span>
                                </div>
                            </div>

                            {/* 6. Complete sale button */}
                            <div className="pt-2">
                                <Button
                                    type="button"
                                    disabled={isSubmittingSale}
                                    onClick={handleCompleteSale}
                                    className="w-full py-6 text-base font-semibold bg-[#1e293b] hover:bg-[#0f172a] text-white rounded-lg shadow-sm"
                                >
                                    Complete sale
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Add Customer Modal */}
            <Dialog open={isCustomerModalOpen} onOpenChange={setIsCustomerModalOpen}>
                <DialogContent className="max-w-md p-6 bg-white text-slate-900">
                    <DialogHeader>
                        <DialogTitle className="text-lg font-bold">Add customer</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSaveCustomer} className="space-y-4 pt-2">
                        <div>
                            <Label htmlFor="new-cust-name" className="text-sm font-medium text-slate-700">
                                Name
                            </Label>
                            <Input
                                id="new-cust-name"
                                type="text"
                                value={custName}
                                onChange={(e) => setCustName(e.target.value)}
                                className="mt-1"
                            />
                            {custError && <p className="text-xs text-red-600 font-medium mt-1">{custError}</p>}
                        </div>
                        <div>
                            <Label htmlFor="new-cust-phone" className="text-sm font-medium text-slate-700">
                                Phone
                            </Label>
                            <Input
                                id="new-cust-phone"
                                type="text"
                                placeholder="0300 0000000"
                                value={custPhone}
                                onChange={(e) => setCustPhone(e.target.value)}
                                className="mt-1 placeholder:text-slate-400"
                            />
                        </div>
                        <div>
                            <Label htmlFor="new-cust-city" className="text-sm font-medium text-slate-700">
                                City
                            </Label>
                            <Input
                                id="new-cust-city"
                                type="text"
                                value={custCity}
                                onChange={(e) => setCustCity(e.target.value)}
                                className="mt-1"
                            />
                        </div>
                        <DialogFooter className="flex gap-2 justify-end pt-3">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    setIsCustomerModalOpen(false);
                                    setCustError('');
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isSavingCustomer}
                                className="bg-[#1e293b] hover:bg-[#0f172a] text-white"
                            >
                                Save customer
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Invoice Window Modal */}
            <InvoiceModal
                isOpen={isInvoiceModalOpen}
                onClose={() => setIsInvoiceModalOpen(false)}
                sale={invoiceModalSale}
                canVoid={can_void}
                canCreatePayment={true}
                onPaymentRecorded={(msg, updatedSale) => {
                    setInvoiceModalSale(updatedSale);
                    showNotification(msg, 'success');
                }}
                onSaleVoided={(msg) => {
                    showNotification(msg, 'success');
                    setIsInvoiceModalOpen(false);
                }}
            />
        </>
    );
}
