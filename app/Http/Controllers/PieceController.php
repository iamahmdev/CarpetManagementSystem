<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PieceController extends Controller
{
    public function index(Request $request): Response
    {
        $permissions = config('roles.'.$request->user()->role->value, []);
        $query = Piece::query()
            ->with(['carpet', 'location'])
            ->when($request->filled('search'), function ($builder) use ($request): void {
                $search = $request->string('search')->toString();
                $builder->where(function ($pieces) use ($search): void {
                    $pieces->where('code', 'like', '%'.$search.'%')
                        ->orWhereHas('carpet', fn ($carpet) => $carpet
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('design', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('location'), fn ($builder) => $builder->where('location_id', $request->integer('location')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()));

        $pieceCode = $request->string('piece_code')->trim()->toString();
        $selectedPiece = null;
        $notFoundMessage = null;
        if ($pieceCode !== '') {
            $normalizedCode = $this->normalizeCode($pieceCode);
            $selectedPieceModel = Piece::query()
                ->with(['carpet', 'location', 'movements.user', 'movements.fromLocation', 'movements.toLocation'])
                ->where('code', $normalizedCode)
                ->first();
            if ($selectedPieceModel) {
                $selectedPiece = $this->pieceDetails($selectedPieceModel, $request);
            } else {
                $notFoundMessage = "No piece with ID {$normalizedCode}.";
            }
        }

        $total = (clone $query)->count();
        $pieces = $query->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (Piece $piece): array => [
                'id' => $piece->id,
                'code' => $piece->code,
                'carpet' => $piece->carpet->name,
                'size' => $piece->carpet->size,
                'location' => $piece->location->name,
                'status' => $piece->status,
                'price' => $piece->effectivePrice(),
                'landed_cost' => in_array('costs.view', $permissions, true)
                    ? $piece->landed_cost
                    : null,
            ]);

        return Inertia::render('pieces/index', [
            'pieces' => $pieces,
            'total' => $total,
            'filters' => $request->only(['search', 'location', 'status']),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Piece::STATUSES,
            'can_view_costs' => in_array('costs.view', $permissions, true),
            'can_create_sales' => in_array('sales.create', $permissions, true),
            'selected_piece' => $selectedPiece,
            'not_found_message' => $notFoundMessage,
        ]);
    }

    private function pieceDetails(Piece $piece, Request $request): array
    {
        $permissions = config('roles.'.$request->user()->role->value, []);

        return [
            'id' => $piece->id,
            'code' => $piece->code,
            'carpet' => $piece->carpet->name,
            'design' => $piece->carpet->design,
            'weave' => $piece->carpet->weave,
            'size' => $piece->carpet->size,
            'color' => $piece->carpet->color,
            'material' => $piece->carpet->material,
            'grade' => $piece->grade,
            'location' => $piece->location->name,
            'location_type' => $piece->location->type,
            'price' => $piece->effectivePrice(),
            'landed_cost' => in_array('costs.view', $permissions, true)
                ? $piece->landed_cost
                : null,
            'container' => $piece->container_id ? (string) $piece->container_id : 'None',
            'status' => $piece->status,
            'can_add_to_sale' => in_array('sales.create', $permissions, true)
                && $piece->status === 'in_stock'
                && $piece->location->type === 'display',
            'history' => $piece->movements
                ->sortByDesc('moved_at')
                ->map(fn (PieceMovement $movement): array => [
                    'text' => $this->movementText($movement),
                    'by' => $movement->user->name,
                    'at' => $movement->moved_at->toIso8601String(),
                ])
                ->values(),
        ];
    }

    private function normalizeCode(string $code): string
    {
        $code = Str::upper(trim($code));
        if (preg_match('/^(?:CP-)?(\d{1,6})$/', $code, $matches) === 1) {
            return 'CP-'.str_pad($matches[1], 6, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    private function movementText(PieceMovement $movement): string
    {
        return match ($movement->type) {
            'receive' => "Received at {$movement->toLocation?->name}",
            'transfer_out' => "Transferred out from {$movement->fromLocation?->name}",
            'transfer_in' => "Transferred in to {$movement->toLocation?->name}",
            'sale' => "Sold at {$movement->fromLocation?->name}",
            'sale_void' => "Sale voided; returned to {$movement->toLocation?->name}",
        };
    }
}
