<?php

namespace App\Http\Controllers;

use App\Models\Carpet;
use App\Models\Location;
use App\Models\Piece;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    public function index(Request $request): Response
    {
        $canViewCosts = in_array(
            'costs.view',
            config('roles.'.$request->user()->role->value, []),
            true,
        );

        // Active locations in the order of the locations list
        $locations = Location::query()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        // Only in_stock pieces at active locations are counted
        $inStockPieces = Piece::query()
            ->where('status', 'in_stock')
            ->whereIn('location_id', $locations->pluck('id'))
            ->with(['carpet', 'location'])
            ->get();

        $piecesByLocation = $inStockPieces->groupBy('location_id');

        // Card 1: Locations table
        $locationsData = $locations->map(function (Location $location) use ($piecesByLocation, $canViewCosts): array {
            $pieces = $piecesByLocation->get($location->id, collect());
            $piecesCount = $pieces->count();
            $listValue = (int) $pieces->sum(fn (Piece $piece): int => $piece->effectivePrice());
            $costValue = $canViewCosts
                ? (int) $pieces->sum('landed_cost')
                : null;

            return [
                'id' => $location->id,
                'name' => $location->name,
                'type' => $location->type, // 'warehouse' or 'display'
                'pieces_count' => $piecesCount,
                'list_value' => $listValue,
                'cost_value' => $costValue,
            ];
        })->values()->all();

        // Card 2: Unique designs in catalog order
        $uniqueDesigns = Carpet::query()
            ->orderBy('id')
            ->pluck('design')
            ->unique()
            ->values();

        $designData = $uniqueDesigns->map(function (string $design) use ($locations, $inStockPieces): array {
            $designPieces = $inStockPieces->filter(fn (Piece $piece): bool => $piece->carpet?->design === $design);

            $countsByLocation = [];
            $totalPieces = 0;

            foreach ($locations as $location) {
                $count = $designPieces->where('location_id', $location->id)->count();
                $countsByLocation[$location->id] = $count;
                $totalPieces += $count;
            }

            return [
                'design' => $design,
                'location_counts' => $countsByLocation,
                'total' => $totalPieces,
                'is_low_stock' => $totalPieces <= 2,
            ];
        })->values()->all();

        return Inertia::render('stock-report/index', [
            'locations' => $locationsData,
            'location_headers' => $locations->map(fn (Location $l): array => [
                'id' => $l->id,
                'name' => $l->name,
            ])->values()->all(),
            'designs' => $designData,
            'can_view_costs' => $canViewCosts,
        ]);
    }
}
