import { Head } from '@inertiajs/react';
import { formatMoney } from '@/lib/ledger';

type ContainerProfitRow = {
    id: number;
    number: string;
    pieces: number;
    sold: number;
    sell_through: number;
    landed_cost: number;
    revenue: number;
    profit: number;
    margin: number;
};

export default function ContainerReport({
    containers,
}: {
    containers: ContainerProfitRow[];
}) {
    return (
        <>
            <Head title="Container profitability" />
            <main className="ledger-page container-report-page p-4">
                <header className="page-head">
                    <div>
                        <h1>Container profitability</h1>
                        <p className="sub">
                            Which imports are paying back, based on real sales
                            and landed cost.
                        </p>
                    </div>
                </header>
                <section className="card ledger-card container-report-card">
                    <div className="tbl-wrap">
                        <table className="container-report-table">
                            <thead>
                                <tr>
                                    <th>Container</th>
                                    <th className="r">Pieces</th>
                                    <th className="r">Sold</th>
                                    <th className="r">Sell-through</th>
                                    <th className="r">Landed cost</th>
                                    <th className="r">Revenue</th>
                                    <th className="r">Profit on sold</th>
                                    <th className="r">Margin</th>
                                </tr>
                            </thead>
                            <tbody>
                                {containers.map((container) => (
                                    <tr key={container.id}>
                                        <td>
                                            <strong>{container.number}</strong>
                                        </td>
                                        <td className="r">{container.pieces}</td>
                                        <td className="r">{container.sold}</td>
                                        <td className="r">
                                            {container.sell_through}%
                                        </td>
                                        <td className="r">
                                            {formatMoney(container.landed_cost)}
                                        </td>
                                        <td className="r">
                                            {formatMoney(container.revenue)}
                                        </td>
                                        <td className="r">
                                            <strong>
                                                {formatMoney(container.profit)}
                                            </strong>
                                        </td>
                                        <td className="r">
                                            {container.margin}%
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {containers.length > 0 && (
                        <p className="container-report-note">
                            Profit on sold pieces only. Unsold stock still
                            carries its cost and is counted once it sells.
                        </p>
                    )}
                </section>
            </main>
        </>
    );
}
