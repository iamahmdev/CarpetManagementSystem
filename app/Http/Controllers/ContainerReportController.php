<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContainerReportController extends Controller
{
    public function index(): Response
    {
        $rows = Container::query()
            ->where('status', 'received')
            ->with(['items', 'pieces'])
            ->orderBy('id')
            ->get()
            ->map(fn (Container $container): array => $this->reportRow($container))
            ->all();

        return Inertia::render('container-report/index', ['containers' => $rows]);
    }

    /**
     * @return array<string, int|string>
     */
    private function reportRow(Container $container): array
    {
        $pieces = $container->pieces;
        $soldPieces = $pieces->where('status', 'sold');
        $soldPieceIds = $soldPieces->pluck('id');
        $soldCost = (int) $soldPieces->sum('landed_cost');
        $revenue = $this->revenueFraction($soldPieceIds);
        $profitNumerator = $revenue['numerator'] - $soldCost * $revenue['denominator'];
        $revenueAmount = $this->roundedRatio($revenue['numerator'], $revenue['denominator']);
        $profitAmount = $this->roundedRatio($profitNumerator, $revenue['denominator']);
        $margin = $revenue['numerator'] === 0
            ? 0
            : $this->roundedRatio($profitNumerator * 100, $revenue['numerator']);

        return [
            'id' => $container->id,
            'number' => $container->number,
            'pieces' => $pieces->count(),
            'sold' => $soldPieces->count(),
            'sell_through' => $pieces->isEmpty()
                ? 0
                : (int) round($soldPieces->count() * 100 / $pieces->count()),
            'landed_cost' => $container->totalLandedCost(),
            'revenue' => $revenueAmount,
            'profit' => $profitAmount,
            'margin' => $margin,
        ];
    }

    /**
     * @param  Collection<int, int>  $soldPieceIds
     * @return array{numerator: int, denominator: int}
     */
    private function revenueFraction(Collection $soldPieceIds): array
    {
        if ($soldPieceIds->isEmpty()) {
            return ['numerator' => 0, 'denominator' => 1];
        }

        $itemsBySale = SaleItem::query()
            ->whereIn('piece_id', $soldPieceIds)
            ->with(['sale' => fn ($query) => $query->where('status', 'completed')])
            ->get()
            ->filter(fn (SaleItem $item): bool => $item->sale !== null)
            ->groupBy('sale_id');

        $revenue = ['numerator' => 0, 'denominator' => 1];
        foreach ($itemsBySale as $items) {
            /** @var Sale $sale */
            $sale = $items->first()->sale;
            if ($sale->subtotal === 0) {
                continue;
            }

            $linePrices = (int) $items->sum('unit_price');
            $revenue = $this->addFractions(
                $revenue,
                ['numerator' => $linePrices * $sale->total, 'denominator' => $sale->subtotal],
            );
        }

        return $revenue;
    }

    /**
     * @param  array{numerator: int, denominator: int}  $left
     * @param  array{numerator: int, denominator: int}  $right
     * @return array{numerator: int, denominator: int}
     */
    private function addFractions(array $left, array $right): array
    {
        $commonDivisor = $this->greatestCommonDivisor($left['denominator'], $right['denominator']);
        $leftMultiplier = intdiv($right['denominator'], $commonDivisor);
        $rightMultiplier = intdiv($left['denominator'], $commonDivisor);
        $numerator = $left['numerator'] * $leftMultiplier
            + $right['numerator'] * $rightMultiplier;
        $denominator = $left['denominator'] * $leftMultiplier;
        $reduction = $this->greatestCommonDivisor(abs($numerator), $denominator);

        return [
            'numerator' => intdiv($numerator, $reduction),
            'denominator' => intdiv($denominator, $reduction),
        ];
    }

    private function greatestCommonDivisor(int $left, int $right): int
    {
        while ($right !== 0) {
            [$left, $right] = [$right, $left % $right];
        }

        return max(1, $left);
    }

    private function roundedRatio(int $numerator, int $denominator): int
    {
        $sign = $numerator < 0 ? -1 : 1;
        $absoluteNumerator = abs($numerator);

        return $sign * intdiv(
            $absoluteNumerator * 2 + $denominator,
            $denominator * 2,
        );
    }
}
