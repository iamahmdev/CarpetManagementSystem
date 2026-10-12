export interface CartItem {
    piece_id: number;
    code: string;
    name: string;
    price: number;
}

export interface SaleCart {
    location_id: number | null;
    items: CartItem[];
    discount: number;
    customer_id: string;
    payment_method: string;
    paid_now: number | null; // null means automatic (equal to total)
    filter_text: string;
}

const STORAGE_KEY = 'carpet_sale_cart';

export function getDefaultCart(defaultLocationId: number | null = null): SaleCart {
    return {
        location_id: defaultLocationId,
        items: [],
        discount: 0,
        customer_id: '',
        payment_method: 'cash',
        paid_now: null,
        filter_text: '',
    };
}

export function loadCart(defaultLocationId: number | null = null): SaleCart {
    if (typeof window === 'undefined') {
        return getDefaultCart(defaultLocationId);
    }
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (!stored) {
            return getDefaultCart(defaultLocationId);
        }
        const parsed = JSON.parse(stored) as Partial<SaleCart>;
        return {
            location_id: parsed.location_id ?? defaultLocationId,
            items: Array.isArray(parsed.items) ? parsed.items : [],
            discount: typeof parsed.discount === 'number' ? parsed.discount : 0,
            customer_id: typeof parsed.customer_id === 'string' ? parsed.customer_id : (parsed.customer_id ? String(parsed.customer_id) : ''),
            payment_method: typeof parsed.payment_method === 'string' ? parsed.payment_method : 'cash',
            paid_now: typeof parsed.paid_now === 'number' ? parsed.paid_now : null,
            filter_text: typeof parsed.filter_text === 'string' ? parsed.filter_text : '',
        };
    } catch {
        return getDefaultCart(defaultLocationId);
    }
}

export function saveCart(cart: SaleCart): void {
    if (typeof window === 'undefined') {
        return;
    }
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    } catch {
        // Ignore storage errors
    }
}

export function clearCartKeepDisplay(displayId: number | null): SaleCart {
    const cleared = getDefaultCart(displayId);
    saveCart(cleared);
    return cleared;
}
