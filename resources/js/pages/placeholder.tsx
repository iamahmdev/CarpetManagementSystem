import { Head } from '@inertiajs/react';

type Props = {
    title: string;
    subtitle: string;
};

export default function Placeholder({ title, subtitle }: Props) {
    return (
        <>
            <Head title={title} />
            <main className="page-placeholder">
                <header className="page-head">
                    <div>
                        <h1>{title}</h1>
                        <p className="sub">{subtitle}</p>
                    </div>
                </header>
            </main>
        </>
    );
}
