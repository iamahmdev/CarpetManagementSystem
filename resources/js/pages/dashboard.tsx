import { Head, Link, usePage } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import { Button } from '@/components/ui/button';
import type { Auth } from '@/types';
import { dashboard } from '@/routes';

type ImportRow = {
    id: number;
    number: string;
    destination: string;
    expected_pieces: number;
    status: 'on_the_way' | 'arrived' | 'received';
};

type Props = {
    auth: Auth;
    can_manage_containers: boolean;
    imports: ImportRow[];
    pieces_in_transit: number;
    transfers_pending: number;
};

const statusLabels: Record<ImportRow['status'], string> = {
    on_the_way: 'On the way',
    arrived: 'Arrived, to receive',
    received: 'Received',
};

const statusClasses: Record<ImportRow['status'], string> = {
    on_the_way: 'b-gray',
    arrived: 'b-amber',
    received: 'b-green',
};

export default function Dashboard() {
    const {
        auth,
        can_manage_containers: canManageContainers,
        imports,
        pieces_in_transit: piecesInTransit = 0,
        transfers_pending: transfersPending = 0,
    } = usePage<Props>().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="dashboard-actions">
                    {canManageContainers && (
                        <Button variant="outline" asChild>
                            <Link href="/containers">Receive container</Link>
                        </Button>
                    )}
                    {auth.permissions.includes('sales.create') && (
                        <Button asChild>
                            <Link href="/new-sale">New sale</Link>
                        </Button>
                    )}
                </div>
                {canManageContainers && (
                    <section className="card dashboard-imports">
                        <header>
                            <h2>Imports from Iran</h2>
                            <Link href="/containers">View all</Link>
                        </header>
                        <div className="dashboard-import-list">
                            {imports.map((container) => (
                                <Link
                                    className="dashboard-import-row"
                                    href={`/containers?container=${container.id}`}
                                    key={container.id}
                                >
                                    <span>
                                        <strong>{container.number}</strong>
                                        <small>
                                            {container.expected_pieces} pieces,{' '}
                                            {container.destination}
                                        </small>
                                    </span>
                                    <span
                                        className={`badge ${statusClasses[container.status]}`}
                                    >
                                        {statusLabels[container.status]}
                                    </span>
                                </Link>
                            ))}
                        </div>
                    </section>
                )}
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 bg-card p-5 dark:border-sidebar-border flex flex-col justify-between">
                        <div>
                            <div className="text-sm font-medium text-muted-foreground">
                                Pieces in transit
                            </div>
                            <div className="mt-2 text-3xl font-bold">
                                {piecesInTransit}
                            </div>
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {transfersPending} transfers pending
                        </div>
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-border" />
                    </div>
                    <div className="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                        <PlaceholderPattern className="absolute inset-0 size-full stroke-border" />
                    </div>
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-border" />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
