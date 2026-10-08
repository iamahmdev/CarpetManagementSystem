import { Head, Link } from '@inertiajs/react';
import { dashboard } from '@/routes';

type Props = {
    status: 403 | 404;
    role: string | null;
    pageLabel: string;
};

export default function ErrorPage({ status, role, pageLabel }: Props) {
    const isForbidden = status === 403;

    return (
        <>
            <Head title={isForbidden ? 'Forbidden' : 'Page not found'} />
            <main className="forbidden">
                <p className="error-code">{status}</p>
                {isForbidden ? (
                    <>
                        <h1>You don't have access to this page</h1>
                        <p>
                            The {role ?? 'Unknown'} role can't open {pageLabel}.
                            Access is enforced on the server as well as in the
                            menu.
                        </p>
                    </>
                ) : (
                    <h1>That page doesn't exist.</h1>
                )}
                <Link href={dashboard()} className="demo-primary-button">
                    Back to the dashboard
                </Link>
            </main>
        </>
    );
}
