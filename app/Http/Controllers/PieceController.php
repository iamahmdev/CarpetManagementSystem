<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                    $pieces->whereRaw('LOWER(code) LIKE ?', ['%'.mb_strtolower($search).'%'])
                        ->orWhereHas('carpet', fn ($carpet) => $carpet
                            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                            ->orWhereRaw('LOWER(design) LIKE ?', ['%'.mb_strtolower($search).'%']));
                });
            })
            ->when($request->filled('location'), fn ($builder) => $builder->where('location_id', $request->integer('location')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->string('status')->toString()));

        $pieceCode = $request->string('piece_code')->trim()->toString();
        $selectedPiece = null;
        $notFoundMessage = null;
        if ($pieceCode !== '') {
            $normalizedCode = Piece::normalizeCode($pieceCode);
            $selectedPieceModel = Piece::query()
                ->with([
                    'carpet',
                    'location',
                    'movements.user',
                    'movements.fromLocation',
                    'movements.toLocation',
                    'movements' => fn ($movements) => $movements
                        ->orderByDesc('moved_at')
                        ->orderByDesc('id'),
                ])
                ->where('code', $normalizedCode)
                ->first();
            if ($selectedPieceModel) {
                $selectedPiece = $this->pieceDetails($selectedPieceModel, $request);
            } else {
                $notFoundMessage = "No piece with ID {$normalizedCode}";
            }
        }

        $total = (clone $query)->count();
        $pieces = $query->orderBy('id')
            ->limit(80)
            ->get()
            ->map(fn (Piece $piece): array => [
                'id' => $piece->id,
                'code' => $piece->code,
                'carpet' => $piece->carpet->name,
                'display_name' => $piece->displayName(),
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

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);
        $code = Piece::normalizeCode($data['code']);
        $piece = Piece::query()->where('code', $code)->first();

        if (! $piece) {
            return response()->json(['message' => "No piece with ID {$code}"], 404);
        }

        $permissions = config('roles.'.$request->user()->role->value, []);
        if (! in_array('stock.view', $permissions, true)) {
            return response()->json([
                'message' => "You don't have access to piece records",
            ], 403);
        }

        return response()->json([
            'url' => route('pieces.index', ['piece_code' => $piece->code]),
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
            'container' => $piece->container_number ?? 'None',
            'status' => $piece->status,
            'can_add_to_sale' => in_array('sales.create', $permissions, true)
                && $piece->status === 'in_stock'
                && $piece->location->type === 'display',
            'history' => $piece->movements
                ->map(fn (PieceMovement $movement): array => [
                    'text' => $this->movementText($movement),
                    'by' => $movement->user->name,
                    'at' => $movement->moved_at->toIso8601String(),
                ])
                ->values(),
        ];
    }

    private function movementText(PieceMovement $movement): string
    {
        $reference = $movement->reference ?? '';

        return match ($movement->type) {
            'receive' => "Received into {$movement->toLocation?->name} ({$reference})",
            'transfer_out' => "Sent from {$movement->fromLocation?->name} to {$movement->toLocation?->name} ({$reference})",
            'transfer_in' => "Arrived at {$movement->toLocation?->name} ({$reference})",
            'sale' => "Sold at {$movement->fromLocation?->name} ({$reference})",
            'sale_void' => "Sale voided, back in stock at {$movement->toLocation?->name} ({$reference})",
        };
    }
}
