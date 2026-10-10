import { Head, router } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { useEffect, useState } from 'react';
import { PieceBarcode } from '@/components/piece-barcode';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatMoney } from '@/lib/ledger';

type ContainerRow = {
    id: number;
    number: string;
    supplier: string;
    destination: string;
    eta_text: string;
    expected_pieces: number;
    status: ContainerStatus;
};

type ContainerStatus = 'on_the_way' | 'arrived' | 'received';

type ContainerItem = {
    id: number;
    carpet: string;
    expected_quantity: number;
    received_quantity: number;
    unit_price?: number;
    landed_cost_each?: number;
};

type ContainerDetails = {
    id: number;
    number: string;
    supplier: string;
    destination: string;
    status: ContainerStatus;
    arrival_text: string;
    items: ContainerItem[];
    costs?: { label: string; amount: number }[];
    total_landed_cost?: number;
};

type Props = {
    containers: ContainerRow[];
    selected_container: ContainerDetails | null;
    show_labels: boolean;
    label_pieces: { code: string; name: string }[];
    label_count: number;
    can_view_costs: boolean;
};

const statusLabels: Record<ContainerStatus, string> = {
    on_the_way: 'On the way',
    arrived: 'Arrived, to receive',
    received: 'Received',
};

const statusClasses: Record<ContainerStatus, string> = {
    on_the_way: 'b-gray',
    arrived: 'b-amber',
    received: 'b-green',
};

function ContainerStatusBadge({ status }: { status: ContainerStatus }) {
    return (
        <span className={`badge ${statusClasses[status]}`}>
            {statusLabels[status]}
        </span>
    );
}

export default function ContainersIndex(props: Props) {
    const [receiveOpen, setReceiveOpen] = useState(false);
    const [quantities, setQuantities] = useState<Record<number, string>>({});
    const container = props.selected_container;

    useEffect(() => {
        if (container) {
            setQuantities(
                Object.fromEntries(
                    container.items.map((item) => [
                        item.id,
                        String(item.expected_quantity),
                    ]),
                ),
            );
        } else {
            setReceiveOpen(false);
        }
    }, [container]);

    function openContainer(id: number) {
        router.get('/containers', { container: id }, { preserveState: true });
    }

    function closeContainer() {
        setReceiveOpen(false);
        router.get('/containers', {}, { preserveState: true, replace: true });
    }

    function markArrived() {
        if (!container) {
            return;
        }

        router.post(`/containers/${container.id}/arrive`);
    }

    function receivePieces() {
        if (!container) {
            return;
        }

        router.post(
            `/containers/${container.id}/receive`,
            { quantities },
            { preserveScroll: true },
        );
    }

    function printLabels() {
        window.print();
    }

    return (
        <>
            <Head title="Containers" />
            <main className="ledger-page containers-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Containers</h1>
                        <p className="sub">
                            Imports from Iran. Receiving a container creates
                            one record per carpet, each with its own ID and
                            label.
                        </p>
                    </div>
                </header>
                <section className="card ledger-card containers-card">
                    <div className="tbl-wrap">
                        <table className="containers-table">
                            <thead>
                                <tr>
                                    <th>Container</th>
                                    <th>Supplier</th>
                                    <th>Goes to</th>
                                    <th>Arrival</th>
                                    <th className="r">Pieces</th>
                                    <th>Status</th>
                                    <th aria-label="Action" />
                                </tr>
                            </thead>
                            <tbody>
                                {props.containers.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="container-row"
                                        tabIndex={0}
                                        role="button"
                                        onClick={() => openContainer(row.id)}
                                        onKeyDown={(event) => {
                                            if (
                                                event.key === 'Enter' ||
                                                event.key === ' '
                                            ) {
                                                event.preventDefault();
                                                openContainer(row.id);
                                            }
                                        }}
                                    >
                                        <td>
                                            <strong>{row.number}</strong>
                                        </td>
                                        <td>{row.supplier}</td>
                                        <td>{row.destination}</td>
                                        <td>{row.eta_text}</td>
                                        <td className="r">
                                            {row.expected_pieces}
                                        </td>
                                        <td>
                                            <ContainerStatusBadge
                                                status={row.status}
                                            />
                                        </td>
                                        <td className="r">
                                            <span className="btn small">
                                                {row.status === 'arrived'
                                                    ? 'Receive'
                                                    : 'Open'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>

            <Dialog
                open={container !== null && !props.show_labels}
                onOpenChange={(open) => {
                    if (!open) {
                        closeContainer();
                    }
                }}
            >
                {container && !props.show_labels && (
                    <DialogContent className="ledger-dialog container-modal max-w-[850px]">
                        <DialogHeader>
                            <DialogTitle>{container.number}</DialogTitle>
                        </DialogHeader>
                        <div className="container-modal-body">
                            <dl className="container-details">
                                <div>
                                    <dt>Supplier</dt>
                                    <dd>{container.supplier}</dd>
                                </div>
                                <div>
                                    <dt>Destination</dt>
                                    <dd>{container.destination}</dd>
                                </div>
                                <div>
                                    <dt>Status</dt>
                                    <dd>
                                        <ContainerStatusBadge
                                            status={container.status}
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt>Arrival</dt>
                                    <dd>{container.arrival_text}</dd>
                                </div>
                            </dl>
                            <div className="tbl-wrap">
                                <table className="containers-items-table">
                                    <thead>
                                        <tr>
                                            <th>Carpet</th>
                                            <th className="r">Expected</th>
                                            <th className="r">Received</th>
                                            {props.can_view_costs && (
                                                <>
                                                    <th className="r">
                                                        Goods cost each
                                                    </th>
                                                    <th className="r">
                                                        Landed cost each
                                                    </th>
                                                </>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {container.items.map((item) => (
                                            <tr key={item.id}>
                                                <td>{item.carpet}</td>
                                                <td className="r">
                                                    {item.expected_quantity}
                                                </td>
                                                <td className="r">
                                                    {item.received_quantity}
                                                </td>
                                                {props.can_view_costs && (
                                                    <>
                                                        <td className="r">
                                                            {formatMoney(
                                                                item.unit_price ??
                                                                    0,
                                                            )}
                                                        </td>
                                                        <td className="r">
                                                            {formatMoney(
                                                                item.landed_cost_each ??
                                                                    0,
                                                            )}
                                                        </td>
                                                    </>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {props.can_view_costs && container.costs && (
                                <section className="container-costs">
                                    <h3>Costs</h3>
                                    <dl>
                                        {container.costs.map((cost) => (
                                            <div key={cost.label}>
                                                <dt>{cost.label}</dt>
                                                <dd>
                                                    {formatMoney(cost.amount)}
                                                </dd>
                                            </div>
                                        ))}
                                        <div className="container-total-cost">
                                            <dt>Total landed cost</dt>
                                            <dd>
                                                {formatMoney(
                                                    container.total_landed_cost ??
                                                        0,
                                                )}
                                            </dd>
                                        </div>
                                    </dl>
                                    <p>
                                        Overheads are spread across pieces in
                                        proportion to their goods cost.
                                    </p>
                                </section>
                            )}
                        </div>
                        <DialogFooter className="container-modal-footer">
                            {container.status === 'on_the_way' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={markArrived}
                                >
                                    Mark as arrived
                                </Button>
                            )}
                            {container.status === 'arrived' && (
                                <Button
                                    type="button"
                                    className="demo-primary-button"
                                    onClick={() => setReceiveOpen(true)}
                                >
                                    Receive into {container.destination}
                                </Button>
                            )}
                            {container.status === 'received' && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() =>
                                        router.get(
                                            '/containers',
                                            {
                                                container: container.id,
                                                labels: 1,
                                            },
                                            { preserveState: true },
                                        )
                                    }
                                >
                                    <Printer />
                                    View labels
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeContainer}
                            >
                                Close
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>

            <Dialog
                open={receiveOpen && container?.status === 'arrived'}
                onOpenChange={setReceiveOpen}
            >
                {container?.status === 'arrived' && (
                    <DialogContent className="ledger-dialog container-modal max-w-[720px]">
                        <DialogHeader>
                            <DialogTitle>Receive {container.number}</DialogTitle>
                        </DialogHeader>
                        <p className="muted">
                            Confirm how many of each carpet came off the
                            container. Each piece will get its own ID.
                        </p>
                        <div className="tbl-wrap">
                            <table className="containers-items-table">
                                <thead>
                                    <tr>
                                        <th>Carpet</th>
                                        <th className="r">Expected</th>
                                        <th className="r">Received</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {container.items.map((item) => (
                                        <tr key={item.id}>
                                            <td>{item.carpet}</td>
                                            <td className="r">
                                                {item.expected_quantity}
                                            </td>
                                            <td className="r">
                                                <input
                                                    type="number"
                                                    min={0}
                                                    max={item.expected_quantity}
                                                    aria-label="Received quantity"
                                                    value={
                                                        quantities[item.id] ??
                                                        String(
                                                            item.expected_quantity,
                                                        )
                                                    }
                                                    onChange={(event) =>
                                                        setQuantities(
                                                            (current) => ({
                                                                ...current,
                                                                [item.id]:
                                                                    event.target
                                                                        .value,
                                                            }),
                                                        )
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <DialogFooter className="container-modal-footer">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setReceiveOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="button"
                                className="demo-primary-button"
                                onClick={receivePieces}
                            >
                                Receive pieces
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>

            <Dialog
                open={props.show_labels && container !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        closeContainer();
                    }
                }}
            >
                {container && props.show_labels && (
                    <DialogContent
                        id="container-labels-print"
                        className="ledger-dialog container-modal labels-modal max-w-[850px]"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                Labels for {container.number}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="container-label-grid">
                            {props.label_pieces.slice(0, 18).map((piece) => (
                                <article
                                    className="container-label-card"
                                    key={piece.code}
                                >
                                    <PieceBarcode code={piece.code} height={36} />
                                    <strong>{piece.code}</strong>
                                    <span>{piece.name}</span>
                                </article>
                            ))}
                        </div>
                        {props.label_count > 18 && (
                            <p className="container-label-count">
                                Showing 18 of {props.label_count} labels.
                            </p>
                        )}
                        <DialogFooter className="container-modal-footer labels-actions">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={printLabels}
                            >
                                <Printer />
                                Print labels
                            </Button>
                            <Button
                                type="button"
                                className="demo-primary-button"
                                onClick={closeContainer}
                            >
                                Done
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                )}
            </Dialog>
        </>
    );
}
