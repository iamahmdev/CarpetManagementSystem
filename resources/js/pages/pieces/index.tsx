import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { formatDateTime, formatMoney } from '@/lib/ledger';

type PieceRow = {
    id: number;
    code: string;
    carpet: string;
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
    const [selectedPiece, setSelectedPiece] =
        useState<PieceDetail | null>(props.selected_piece);

    useEffect(() => {
        setSelectedPiece(props.selected_piece);
    }, [props.selected_piece]);

    function filterPieces(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const formData = new FormData(event.currentTarget);
        router.get(
            '/pieces',
            {
                search: search || undefined,
                location: String(formData.get('location') ?? '') || undefined,
                status: String(formData.get('status') ?? '') || undefined,
            },
            { preserveState: true, replace: true },
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
            <main className="ledger-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Pieces</h1>
                        <p className="sub">
                            Every physical carpet, tracked by its own ID.
                        </p>
                    </div>
                </header>
                <section className="card ledger-card">
                    <form className="ledger-filters" onSubmit={filterPieces}>
                        <input
                            type="search"
                            aria-label="Search pieces"
                            placeholder="Search code, carpet name, or design"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                        />
                        <select
                            name="location"
                            aria-label="Filter by location"
                            defaultValue={props.filters.location ?? ''}
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
                            defaultValue={props.filters.status ?? ''}
                        >
                            <option value="">All statuses</option>
                            {props.statuses.map((status) => (
                                <option key={status} value={status}>
                                    {statusLabels[status]}
                                </option>
                            ))}
                        </select>
                        <Button type="submit" variant="outline">
                            Apply filters
                        </Button>
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
                        <table>
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
                                    <tr key={piece.id}>
                                        <td>
                                            <button
                                                type="button"
                                                className="ledger-link"
                                                onClick={() =>
                                                    showPiece(piece.code)
                                                }
                                            >
                                                {piece.code}
                                            </button>
                                        </td>
                                        <td>
                                            <strong>{piece.carpet}</strong>
                                            <div className="muted">
                                                {piece.size}
                                            </div>
                                        </td>
                                        <td>{piece.location}</td>
                                        <td>
                                            <PieceStatus status={piece.status} />
                                        </td>
                                        <td className="r">
                                            {formatMoney(piece.price)}
                                        </td>
                                        {props.can_view_costs && (
                                            <td className="r">
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
                                Piece {selectedPiece.code}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="ledger-piece-detail">
                            <div className="ledger-piece-barcode">
                                {selectedPiece.code}
                            </div>
                            <PieceStatus status={selectedPiece.status} />
                            <dl>
                                <div><dt>Carpet</dt><dd>{selectedPiece.carpet}</dd></div>
                                <div><dt>Design</dt><dd>{selectedPiece.design}</dd></div>
                                <div><dt>Weave</dt><dd>{selectedPiece.weave}</dd></div>
                                <div><dt>Size</dt><dd>{selectedPiece.size}</dd></div>
                                <div><dt>Color</dt><dd>{selectedPiece.color}</dd></div>
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
                                        <span>by {movement.by}</span>
                                        <time>{formatDateTime(movement.at)}</time>
                                    </li>
                                ))}
                                {selectedPiece.history.length === 0 && (
                                    <li>No movement history.</li>
                                )}
                            </ol>
                        </div>
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
