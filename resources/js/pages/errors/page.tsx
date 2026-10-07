import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes';

type Props = {
    status: 403 | 404;
};

export default function ErrorPage({ status }: Props) {
    const isForbidden = status === 403;

    return (
        <>
            <Head title={isForbidden ? 'Forbidden' : 'Page not found'} />
            <main className="flex min-h-svh items-center justify-center bg-background px-6 py-12">
                <div className="max-w-md space-y-4 text-center">
                    <p className="text-sm font-semibold tracking-widest text-muted-foreground">
                        {status}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {isForbidden
                            ? "You don't have access to this page"
                            : 'Page not found'}
                    </h1>
                    <Link
                        href={dashboard()}
                        className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition-colors hover:bg-primary/90"
                    >
                        Back to Dashboard
                    </Link>
                </div>
            </main>
        </>
    );
}
