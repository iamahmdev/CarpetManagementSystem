import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type TransferRow = {
    id: number;
    reference: string;
    from_name: string;
    to_name: string;
    route: string;
    pieces_count: number;
    piece_codes: string[];
    sent_date: string;
    sent_at: string;
    status: 'in_transit' | 'received';
};

type LocationOption = {
    id: number;
    name: string;
    parent_id?: number | null;
};

type PieceOption = {
    id: number;
    code: string;
    carpet: string;
};

type Props = {
    transfers: TransferRow[];
    warehouses: LocationOption[];
    displays: LocationOption[];
    pieces: PieceOption[];
};

export default function TransfersIndex(props: Props) {
    const [createOpen, setCreateOpen] = useState(false);
    const [receivingId, setReceivingId] = useState<number | null>(null);

    const initialWarehouseId = props.warehouses[0]?.id ?? 0;
    const initialAdjacentDisplay =
        props.displays.find((d) => d.parent_id === initialWarehouseId)?.id ??
        props.displays[0]?.id ??
        0;

    const [fromLocationId, setFromLocationId] = useState(initialWarehouseId);
    const [toLocationId, setToLocationId] = useState(initialAdjacentDisplay);
    const [availablePieces, setAvailablePieces] = useState<PieceOption[]>(props.pieces);
    const [selectedPieceIds, setSelectedPieceIds] = useState<number[]>([]);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isLoadingPieces, setIsLoadingPieces] = useState(false);

    useEffect(() => {
        setAvailablePieces(props.pieces);
    }, [props.pieces]);

    function handleFromChange(warehouseId: number) {
        setFromLocationId(warehouseId);
        setErrorMessage(null);
        setSelectedPieceIds([]);

        const adjacentDisplay = props.displays.find((d) => d.parent_id === warehouseId);
        if (adjacentDisplay) {
            setToLocationId(adjacentDisplay.id);
        } else if (props.displays.length > 0) {
            setToLocationId(props.displays[0].id);
        }

        setIsLoadingPieces(true);
        fetch(`/transfers/pieces?warehouse_id=${warehouseId}`, {
            headers: { Accept: 'application/json' },
        })
            .then((res) => res.json())
            .then((data) => {
                setAvailablePieces(data.pieces ?? []);
            })
            .catch(() => {
                setAvailablePieces([]);
            })
            .finally(() => {
                setIsLoadingPieces(false);
            });
    }

    function openModal() {
        setFromLocationId(initialWarehouseId);
        setToLocationId(initialAdjacentDisplay);
        setSelectedPieceIds([]);
        setErrorMessage(null);
        setIsSubmitting(false);

        if (initialWarehouseId) {
            handleFromChange(initialWarehouseId);
        }
        setCreateOpen(true);
    }

    function toggleSelectAll() {
        if (selectedPieceIds.length === availablePieces.length) {
            setSelectedPieceIds([]);
        } else {
            setSelectedPieceIds(availablePieces.map((p) => p.id));
        }
        setErrorMessage(null);
    }

    function togglePiece(pieceId: number) {
        if (selectedPieceIds.includes(pieceId)) {
            setSelectedPieceIds(selectedPieceIds.filter((id) => id !== pieceId));
        } else {
            setSelectedPieceIds([...selectedPieceIds, pieceId]);
        }
        setErrorMessage(null);
    }

    function handleCreateTransfer(event: React.FormEvent) {
        event.preventDefault();

        if (selectedPieceIds.length === 0) {
            setErrorMessage('Select at least one piece to send');
            return;
        }

        setIsSubmitting(true);
        setErrorMessage(null);

        router.post(
            '/transfers',
            {
                from_location_id: fromLocationId,
                to_location_id: toLocationId,
                piece_ids: selectedPieceIds,
            },
            {
                onSuccess: () => {
                    setCreateOpen(false);
                    setSelectedPieceIds([]);
                },
                onError: (errors) => {
                    setErrorMessage(
                        (errors.piece_ids as string) ??
                            Object.values(errors)[0] ??
                            'Failed to create transfer.',
                    );
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            },
        );
    }

    function handleReceive(transferId: number) {
        setReceivingId(transferId);
        router.post(
            `/transfers/${transferId}/receive`,
            {},
            {
                onFinish: () => {
                    setReceivingId(null);
                },
            },
        );
    }

    return (
        <>
            <Head title="Transfers" />
            <div className="flex flex-1 flex-col p-4 md:p-8">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-foreground">
                            Transfers
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Move pieces from a warehouse to its display. Stock only changes location
                            when the other side confirms.
                        </p>
                    </div>
                    <div>
                        <Button
                            type="button"
                            onClick={openModal}
                            className="bg-[#1e293b] text-white hover:bg-[#0f172a] dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                        >
                            <Plus className="mr-1.5 h-4 w-4" />
                            New transfer
                        </Button>
                    </div>
                </div>

                <div className="mt-6 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b border-border text-xs font-semibold text-muted-foreground">
                                    <th className="px-6 py-4">Reference</th>
                                    <th className="px-6 py-4">Route</th>
                                    <th className="px-6 py-4">Pieces</th>
                                    <th className="px-6 py-4">Sent</th>
                                    <th className="px-6 py-4">Status</th>
                                    <th className="px-6 py-4 text-right">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {props.transfers.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="px-6 py-12 text-center text-muted-foreground"
                                        >
                                            No transfers yet.
                                        </td>
                                    </tr>
                                ) : (
                                    props.transfers.map((transfer) => {
                                        const displayPieceCodes =
                                            transfer.piece_codes.slice(0, 4).join(', ') +
                                            (transfer.piece_codes.length > 4 ? '...' : '');

                                        return (
                                            <tr
                                                key={transfer.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                <td className="px-6 py-4 font-bold text-foreground">
                                                    {transfer.reference}
                                                </td>
                                                <td className="px-6 py-4">
                                                    <div className="font-medium text-foreground">
                                                        {transfer.route}
                                                    </div>
                                                    {transfer.piece_codes.length > 0 && (
                                                        <div className="mt-0.5 text-xs text-muted-foreground">
                                                            {displayPieceCodes}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 font-medium text-foreground">
                                                    {transfer.pieces_count}
                                                </td>
                                                <td className="px-6 py-4 text-muted-foreground">
                                                    {transfer.sent_date}
                                                </td>
                                                <td className="px-6 py-4">
                                                    {transfer.status === 'in_transit' ? (
                                                        <span className="inline-flex items-center rounded-full bg-[#fef3c7] px-2.5 py-0.5 text-xs font-medium text-[#92400e] dark:bg-amber-950/50 dark:text-amber-400">
                                                            In transit
                                                        </span>
                                                    ) : (
                                                        <span className="inline-flex items-center rounded-full bg-[#dcfce7] px-2.5 py-0.5 text-xs font-medium text-[#166534] dark:bg-emerald-950/50 dark:text-emerald-400">
                                                            Received
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 text-right">
                                                    {transfer.status === 'in_transit' && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            onClick={() => handleReceive(transfer.id)}
                                                            disabled={receivingId === transfer.id}
                                                            className="h-auto rounded-md bg-[#1e293b] px-3.5 py-1.5 text-xs font-medium text-white shadow-sm hover:bg-[#0f172a] dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                                                        >
                                                            {receivingId === transfer.id
                                                                ? 'Receiving...'
                                                                : 'Mark received'}
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>New transfer</DialogTitle>
                    </DialogHeader>

                    <form onSubmit={handleCreateTransfer} className="space-y-4">
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <Label htmlFor="from-warehouse" className="text-xs font-medium">
                                    From warehouse
                                </Label>
                                <select
                                    id="from-warehouse"
                                    value={fromLocationId}
                                    onChange={(e) => handleFromChange(Number(e.target.value))}
                                    className="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {props.warehouses.map((warehouse) => (
                                        <option key={warehouse.id} value={warehouse.id}>
                                            {warehouse.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Label htmlFor="to-display" className="text-xs font-medium">
                                    To display
                                </Label>
                                <select
                                    id="to-display"
                                    value={toLocationId}
                                    onChange={(e) => setToLocationId(Number(e.target.value))}
                                    className="mt-1.5 flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                >
                                    {props.displays.map((display) => (
                                        <option key={display.id} value={display.id}>
                                            {display.name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div>
                            <div className="mb-2 flex items-center justify-between">
                                <Label className="text-xs font-medium">
                                    Choose the pieces to send
                                </Label>
                                {availablePieces.length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        {selectedPieceIds.length} of {availablePieces.length} selected
                                    </span>
                                )}
                            </div>

                            <div className="max-h-60 overflow-y-auto rounded-md border border-border">
                                {isLoadingPieces ? (
                                    <div className="p-8 text-center text-sm text-muted-foreground">
                                        Loading pieces...
                                    </div>
                                ) : availablePieces.length === 0 ? (
                                    <div className="p-8 text-center text-sm text-muted-foreground">
                                        No pieces in stock at this warehouse.
                                    </div>
                                ) : (
                                    <table className="w-full text-left text-xs">
                                        <thead className="sticky top-0 border-b border-border bg-muted/50 font-semibold text-muted-foreground">
                                            <tr>
                                                <th className="w-10 px-3 py-2 text-center">
                                                    <input
                                                        type="checkbox"
                                                        aria-label="Select all"
                                                        checked={
                                                            availablePieces.length > 0 &&
                                                            selectedPieceIds.length ===
                                                                availablePieces.length
                                                        }
                                                        onChange={toggleSelectAll}
                                                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                    />
                                                </th>
                                                <th className="px-3 py-2">ID</th>
                                                <th className="px-3 py-2">Carpet</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-border">
                                            {availablePieces.map((piece) => {
                                                const isSelected = selectedPieceIds.includes(
                                                    piece.id,
                                                );
                                                return (
                                                    <tr
                                                        key={piece.id}
                                                        onClick={() => togglePiece(piece.id)}
                                                        className={`cursor-pointer hover:bg-muted/40 ${
                                                            isSelected ? 'bg-muted/25' : ''
                                                        }`}
                                                    >
                                                        <td
                                                            className="w-10 px-3 py-2 text-center"
                                                            onClick={(e) => e.stopPropagation()}
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                aria-label={`Select ${piece.code}`}
                                                                checked={isSelected}
                                                                onChange={() => togglePiece(piece.id)}
                                                                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2 font-mono font-medium text-foreground">
                                                            {piece.code}
                                                        </td>
                                                        <td className="px-3 py-2 text-foreground">
                                                            {piece.carpet}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                )}
                            </div>
                        </div>

                        {errorMessage && (
                            <p className="text-xs font-medium text-destructive" role="alert">
                                {errorMessage}
                            </p>
                        )}

                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateOpen(false)}
                                disabled={isSubmitting}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={isSubmitting}
                                className="bg-[#1e293b] text-white hover:bg-[#0f172a] dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90"
                            >
                                {isSubmitting ? 'Creating...' : 'Create transfer'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

TransfersIndex.layout = {
    breadcrumbs: [
        {
            title: 'Transfers',
            href: '/transfers',
        },
    ],
};
