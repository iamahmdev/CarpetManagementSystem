export type DemoIconName =
    | 'dashboard'
    | 'carpets'
    | 'pieces'
    | 'containers'
    | 'transfers'
    | 'sale'
    | 'history'
    | 'customers'
    | 'stock'
    | 'profitab'
    | 'profit'
    | 'receivables'
    | 'profile'
    | 'locations'
    | 'roles'
    | 'search'
    | 'menu';

export function DemoIcon({
    name,
    size = 16,
}: {
    name: DemoIconName;
    size?: number;
}) {
    return (
        <svg
            className="ico"
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {name === 'dashboard' && (
                <>
                    <rect x="3" y="3" width="7" height="9" rx="1" />
                    <rect x="14" y="3" width="7" height="5" rx="1" />
                    <rect x="14" y="12" width="7" height="9" rx="1" />
                    <rect x="3" y="16" width="7" height="5" rx="1" />
                </>
            )}
            {name === 'carpets' && (
                <>
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <rect x="7" y="9" width="10" height="6" />
                </>
            )}
            {name === 'pieces' && (
                <path d="M4 5v14M8 5v14M12 5v14M16 5v14M20 5v14" />
            )}
            {name === 'containers' && (
                <>
                    <rect x="3" y="7" width="18" height="11" rx="1" />
                    <path d="M8 7v11M12 7v11M16 7v11" />
                </>
            )}
            {name === 'transfers' && (
                <path d="M4 8h14M14 4l4 4-4 4M20 16H6M10 12l-4 4 4 4" />
            )}
            {name === 'sale' && (
                <>
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 8v8M8 12h8" />
                </>
            )}
            {name === 'history' && (
                <>
                    <circle cx="12" cy="12" r="9" />
                    <path d="M12 7v5l3 2" />
                </>
            )}
            {name === 'customers' && (
                <>
                    <circle cx="9" cy="8" r="3.5" />
                    <path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6M16 5a3.5 3.5 0 010 6.5M18 14c2 .8 3 2.6 3 5" />
                </>
            )}
            {name === 'stock' && <path d="M5 20V10M12 20V4M19 20v-7" />}
            {name === 'profitab' && (
                <>
                    <path d="M12 3v9h9" />
                    <circle cx="12" cy="12" r="9" />
                </>
            )}
            {name === 'profit' && <path d="M3 17l6-6 4 4 8-8M15 7h6v6" />}
            {name === 'receivables' && (
                <>
                    <rect x="3" y="5" width="18" height="14" rx="2" />
                    <path d="M7 9h10M7 13h4M16 13h1" />
                </>
            )}
            {name === 'profile' && (
                <>
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" />
                </>
            )}
            {name === 'locations' && (
                <>
                    <path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z" />
                    <circle cx="12" cy="10" r="2.5" />
                </>
            )}
            {name === 'roles' && (
                <path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z" />
            )}
            {name === 'search' && (
                <>
                    <circle cx="11" cy="11" r="7" />
                    <path d="M20 20l-4-4" />
                </>
            )}
            {name === 'menu' && <path d="M4 6h16M4 12h16M4 18h16" />}
        </svg>
    );
}
