import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/ledger';
import { PieceBarcode } from '@/components/piece-barcode';

type PieceRow = {
    id: number;
    code: string;
    carpet: string;
    display_name: string;
    size: string;
    location: string;
    status: string;
    price: number;
    landed_cost: number | null;
};

type PieceDetail = PieceRow & {
    design: string;
    weave: string;
    color: string;
    material: string;
    grade: 'A' | 'B';
    location_type: string;
    container: string;
    can_add_to_sale: boolean;
    history: { text: string; by: string; at: string }[];
};

type Props = {
    pieces: PieceRow[];
    total: number;
    filters: { search?: string; location?: string; status?: string };
    locations: { id: number; name: string }[];
    statuses: string[];
    can_view_costs: boolean;
    can_create_sales: boolean;
    selected_piece: PieceDetail | null;
    not_found_message: string | null;
};

const statusLabels: Record<string, string> = {
    in_stock: 'In stock',
    in_transit: 'In transit',
    sold: 'Sold',
    damaged: 'Damaged',
    reserved: 'Reserved',
};

const statusClasses: Record<string, string> = {
    in_stock: 'b-green',
    in_transit: 'b-amber',
    sold: 'b-gray',
    damaged: 'b-red',
    reserved: 'b-indigo',
};

export default function PiecesIndex(props: Props) {
    const [search, setSearch] = useState(props.filters.search ?? '');
    const [locationFilter, setLocationFilter] = useState(
        props.filters.location ?? '',
    );
    const [statusFilter, setStatusFilter] = useState(
        props.filters.status ?? '',
    );
    const [selectedPiece, setSelectedPiece] =
        useState<PieceDetail | null>(props.selected_piece);
    const isInitialFilterRender = useRef(true);

    useEffect(() => {
        setSearch(props.filters.search ?? '');
        setLocationFilter(props.filters.location ?? '');
        setStatusFilter(props.filters.status ?? '');
    }, [props.filters.search, props.filters.location, props.filters.status]);

    useEffect(() => {
        if (isInitialFilterRender.current) {
            isInitialFilterRender.current = false;

            return;
        }

        const timeout = window.setTimeout(() => {
            router.get(
                '/pieces',
                {
                    search: search || undefined,
                    location: locationFilter || undefined,
                    status: statusFilter || undefined,
                },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 250);

        return () => window.clearTimeout(timeout);
    }, [search, locationFilter, statusFilter]);

    useEffect(() => {
        setSelectedPiece(props.selected_piece);
    }, [props.selected_piece]);

    function filterPieces(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/pieces',
            {
                search: search || undefined,
                location: locationFilter || undefined,
                status: statusFilter || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function closePiece(open: boolean) {
        if (open) {
            return;
        }
        setSelectedPiece(null);
        router.get('/pieces', props.filters, {
            preserveState: true,
            replace: true,
        });
    }

    function showPiece(code: string) {
        router.get(
            '/pieces',
            { ...props.filters, piece_code: code },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Pieces" />
            <main className="ledger-page pieces-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Pieces</h1>
                        <p className="sub">
                            Every physical carpet with its own ID. Search by
                            code, location, or status.
                        </p>
                    </div>
                </header>
                <section className="card ledger-card pieces-card">
                    <form className="pieces-filters" onSubmit={filterPieces}>
                        <input
                            type="search"
                            aria-label="Search pieces"
                            placeholder="Search by ID, carpet, or design"
                            autoComplete="off"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <select
                            name="location"
                            aria-label="Filter by location"
                            value={locationFilter}
                            onChange={(event) =>
                                setLocationFilter(event.target.value)
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
                            name="status"
                            aria-label="Filter by status"
                            value={statusFilter}
                            onChange={(event) =>
                                setStatusFilter(event.target.value)
                            }
                        >
                            <option value="">All statuses</option>
                            {props.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {statusLabels[status]}
                                </option>
                            ))}
                        </select>
                    </form>
                    {props.not_found_message && (
                        <p className="ledger-validation-error" role="status">
                            {props.not_found_message}
                        </p>
                    )}
                    {props.total > 80 && (
                        <p className="muted">
                            Showing 80 of {props.total}. Narrow the filters to
                            see more.
                        </p>
                    )}
                    <div className="tbl-wrap">
                        <table className="pieces-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Carpet</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th className="r">Price</th>
                                    {props.can_view_costs && (
                                        <th className="r">Cost</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {props.pieces.map((piece) => (
                                    <tr
                                        key={piece.id}
                                        className="ledger-piece-row"
                                        role="button"
                                        tabIndex={0}
                                        onClick={() => showPiece(piece.code)}
                                        onKeyDown={(event) => {
                                            if (
                                                event.key === 'Enter' ||
                                                event.key === ' '
                                            ) {
                                                event.preventDefault();
                                                showPiece(piece.code);
                                            }
                                        }}
                                    >
                                        <td>
                                            <strong className="piece-code">
                                                {piece.code}
                                            </strong>
                                        </td>
                                        <td>{piece.display_name}</td>
                                        <td>{piece.location}</td>
                                        <td>
                                            <PieceStatus status={piece.status} />
                                        </td>
                                        <td className="r">
                                            {formatMoney(piece.price)}
                                        </td>
                                        {props.can_view_costs && (
                                            <td className="r muted">
                                                {formatMoney(
                                                    piece.landed_cost ?? 0,
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {props.pieces.length === 0 && (
                                    <tr>
                                        <td
                                            className="empty"
                                            colSpan={
                                                props.can_view_costs ? 6 : 5
                                            }
                                        >
                                            No pieces match these filters.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <Dialog
                open={selectedPiece !== null}
                onOpenChange={closePiece}
            >
                {selectedPiece && (
                    <DialogContent className="ledger-dialog max-w-[650px]">
                        <DialogHeader>
                            <DialogTitle>
                                {selectedPiece.code}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="ledger-piece-detail">
                            <div className="ledger-piece-top">
                                <PieceBarcode code={selectedPiece.code} />
                                <PieceStatus status={selectedPiece.status} />
                            </div>
                            <dl>
                                <div><dt>Carpet</dt><dd><strong>{selectedPiece.carpet}</strong></dd></div>
                                <div><dt>Design</dt><dd>{selectedPiece.design}, {selectedPiece.weave}</dd></div>
                                <div><dt>Size and color</dt><dd>{selectedPiece.size}, {selectedPiece.color}</dd></div>
                                <div><dt>Material</dt><dd>{selectedPiece.material}</dd></div>
                                <div><dt>Grade</dt><dd>{selectedPiece.grade}</dd></div>
                                <div><dt>Location</dt><dd>{selectedPiece.location}</dd></div>
                                <div><dt>Price</dt><dd>{formatMoney(selectedPiece.price)}</dd></div>
                                {props.can_view_costs && (
                                    <div><dt>Landed cost</dt><dd>{formatMoney(selectedPiece.landed_cost ?? 0)}</dd></div>
                                )}
                                <div><dt>Container</dt><dd>{selectedPiece.container}</dd></div>
                            </dl>
                            <h3>History</h3>
                            <ol className="ledger-piece-history">
                                {selectedPiece.history.map((movement, index) => (
                                    <li key={`${movement.at}-${index}`}>
                                        <strong>{movement.text}</strong>
                                        <time>{formatDateTime(movement.at)} by {movement.by}</time>
                                    </li>
                                ))}
                                {selectedPiece.history.length === 0 && (
                                    <li>No movement history.</li>
                                )}
                            </ol>
                        </div>
                        <div className="ledger-piece-actions">
                            {props.can_create_sales &&
                                selectedPiece.can_add_to_sale && (
                                    <Button asChild className="demo-primary-button">
                                        <Link
                                            href={`/new-sale?piece_id=${selectedPiece.id}`}
                                        >
                                            Add to sale
                                        </Link>
                                    </Button>
                                )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => closePiece(false)}
                            >
                                Close
                            </Button>
                        </div>
                    </DialogContent>
                )}
            </Dialog>
        </>
    );
}

function PieceStatus({ status }: { status: string }) {
    return (
        <span className={`badge ${statusClasses[status] ?? 'b-info'}`}>
            {statusLabels[status] ?? status}
        </span>
    );
}
