import { Head } from '@inertiajs/react';
import { formatMoney } from '@/lib/ledger';

type LocationStockRow = {
    id: number;
    name: string;
    type: 'warehouse' | 'display' | string;
    pieces_count: number;
    list_value: number;
    cost_value: number | null;
};

type LocationHeader = {
    id: number;
    name: string;
};

type DesignStockRow = {
    design: string;
    location_counts: Record<number, number>;
    total: number;
    is_low_stock: boolean;
};

type Props = {
    locations: LocationStockRow[];
    location_headers: LocationHeader[];
    designs: DesignStockRow[];
    can_view_costs: boolean;
};

export default function StockReportIndex({
    locations,
    location_headers,
    designs,
    can_view_costs,
}: Props) {
    function renderLocationTypeBadge(type: string) {
        if (type.toLowerCase() === 'warehouse') {
            return (
                <span className="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full bg-[#eef2ff] text-[#4338ca]">
                    Warehouse
                </span>
            );
        }

        return (
            <span className="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full bg-[#fef7e0] text-[#b06000]">
                Display
            </span>
        );
    }

    return (
        <>
            <Head title="Stock by location" />

            <div className="flex flex-1 flex-col w-full p-6 md:p-8 space-y-6">
                {/* Page Header (No header buttons) */}
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-slate-900">
                        Stock by location
                    </h1>
                    <p className="text-sm text-slate-500 mt-1">
                        What is on hand right now, at each warehouse and display.
                    </p>
                </div>

                {/* CARD 1: LOCATIONS TABLE */}
                <div className="w-full bg-white rounded-xl border border-slate-200/80 p-6 shadow-xs">
                    <div className="w-full overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs font-semibold text-slate-500">
                                    <th className="py-3 px-4 font-semibold">Location</th>
                                    <th className="py-3 px-4 font-semibold">Type</th>
                                    <th className="py-3 px-4 text-right font-semibold">Pieces</th>
                                    <th className="py-3 px-4 text-right font-semibold">Value at list price</th>
                                    {can_view_costs && (
                                        <th className="py-3 px-4 text-right font-semibold">Cost value</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {locations.map((loc) => (
                                    <tr key={loc.id} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="py-3.5 px-4 font-bold text-slate-900">
                                            {loc.name}
                                        </td>
                                        <td className="py-3.5 px-4 whitespace-nowrap">
                                            {renderLocationTypeBadge(loc.type)}
                                        </td>
                                        <td className="py-3.5 px-4 text-right text-slate-700 whitespace-nowrap">
                                            {loc.pieces_count}
                                        </td>
                                        <td className="py-3.5 px-4 text-right text-slate-900 whitespace-nowrap">
                                            {formatMoney(loc.list_value)}
                                        </td>
                                        {can_view_costs && (
                                            <td className="py-3.5 px-4 text-right text-slate-900 whitespace-nowrap">
                                                {loc.cost_value !== null ? formatMoney(loc.cost_value) : '—'}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* CARD 2: DESIGN BY LOCATION */}
                <div className="w-full bg-white rounded-xl border border-slate-200/80 p-6 shadow-xs space-y-4">
                    {/* Card 2 Heading and Note */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 pb-2 border-b border-slate-100">
                        <h2 className="text-lg font-bold text-slate-900">
                            Design by location
                        </h2>
                        <span className="text-xs text-slate-400">
                            Designs with two or fewer pieces are flagged
                        </span>
                    </div>

                    <div className="w-full overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-slate-100 text-left text-xs font-semibold text-slate-500">
                                    <th className="py-3 px-4 font-semibold">Design</th>
                                    {location_headers.map((loc) => (
                                        <th
                                            key={loc.id}
                                            className="py-3 px-4 text-right font-semibold whitespace-nowrap"
                                        >
                                            {loc.name}
                                        </th>
                                    ))}
                                    <th className="py-3 px-4 text-right font-semibold">Total</th>
                                    <th className="py-3 px-4 w-28">
                                        <span className="sr-only">Flag</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {designs.map((row) => (
                                    <tr key={row.design} className="hover:bg-slate-50/50 transition-colors">
                                        <td className="py-3.5 px-4 font-bold text-slate-900">
                                            {row.design}
                                        </td>
                                        {location_headers.map((loc) => (
                                            <td
                                                key={loc.id}
                                                className="py-3.5 px-4 text-right text-slate-700 whitespace-nowrap"
                                            >
                                                {row.location_counts[loc.id] ?? 0}
                                            </td>
                                        ))}
                                        <td className="py-3.5 px-4 text-right font-bold text-slate-900 whitespace-nowrap">
                                            {row.total}
                                        </td>
                                        <td className="py-3.5 px-4 text-right whitespace-nowrap">
                                            {row.is_low_stock && (
                                                <span className="inline-block px-2.5 py-0.5 text-xs font-medium rounded-full bg-[#fce8e6] text-[#c5221f]">
                                                    Low stock
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </>
    );
}
