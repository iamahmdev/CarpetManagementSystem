<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Piece;
use App\Models\Transfer;
use App\Services\PieceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(): Response
    {
        $transfers = Transfer::query()
            ->with([
                'fromLocation',
                'toLocation',
                'pieces' => fn ($query) => $query->select('pieces.id', 'pieces.code')->orderBy('pieces.id'),
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Transfer $transfer): array => [
                'id' => $transfer->id,
                'reference' => $transfer->reference,
                'from_name' => $transfer->fromLocation->name,
                'to_name' => $transfer->toLocation->name,
                'route' => "{$transfer->fromLocation->name} to {$transfer->toLocation->name}",
                'pieces_count' => $transfer->pieces->count(),
                'piece_codes' => $transfer->pieces->map(fn (Piece $piece): string => $piece->code)->values()->all(),
                'sent_date' => $transfer->sent_at->format('j M'),
                'sent_at' => $transfer->sent_at->toIso8601String(),
                'status' => $transfer->status,
            ]);

        $warehouses = Location::query()
            ->warehouses()
            ->active()
            ->orderBy('id')
            ->get(['id', 'name']);

        $displays = Location::query()
            ->displays()
            ->active()
            ->orderBy('id')
            ->get(['id', 'name', 'parent_id']);

        $defaultWarehouse = $warehouses->first();
        $inStockPieces = $defaultWarehouse
            ? Piece::query()
                ->with('carpet')
                ->where('location_id', $defaultWarehouse->id)
                ->where('status', 'in_stock')
                ->orderBy('id')
                ->get()
                ->map(fn (Piece $piece): array => [
                    'id' => $piece->id,
                    'code' => $piece->code,
                    'carpet' => $piece->carpet->name,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('transfers/index', [
            'transfers' => $transfers,
            'warehouses' => $warehouses,
            'displays' => $displays,
            'pieces' => $inStockPieces,
        ]);
    }

    public function pieces(Request $request): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id');

        $pieces = Piece::query()
            ->with('carpet')
            ->where('location_id', $warehouseId)
            ->where('status', 'in_stock')
            ->orderBy('id')
            ->get()
            ->map(fn (Piece $piece): array => [
                'id' => $piece->id,
                'code' => $piece->code,
                'carpet' => $piece->carpet->name,
            ])
            ->values();

        return response()->json(['pieces' => $pieces]);
    }

    public function store(Request $request, PieceService $pieceService): RedirectResponse
    {
        $pieceIds = $request->input('piece_ids');
        if (empty($pieceIds) || ! is_array($pieceIds)) {
            return back()->withErrors(['piece_ids' => 'Select at least one piece to send']);
        }

        $validated = $request->validate([
            'from_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('type', 'warehouse')->where('active', true),
            ],
            'to_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('type', 'display')->where('active', true),
            ],
            'piece_ids' => ['required', 'array', 'min:1'],
            'piece_ids.*' => ['integer'],
        ]);

        $fromLocation = Location::query()->findOrFail($validated['from_location_id']);
        $toLocation = Location::query()->findOrFail($validated['to_location_id']);

        $selectedPieceIds = array_map('intval', $validated['piece_ids']);
        $pieces = Piece::query()
            ->whereIn('id', $selectedPieceIds)
            ->where('location_id', $fromLocation->id)
            ->where('status', 'in_stock')
            ->get();

        if ($pieces->count() !== count($selectedPieceIds)) {
            return back()->withErrors([
                'piece_ids' => 'Select at least one piece to send',
            ]);
        }

        return DB::transaction(function () use ($request, $fromLocation, $toLocation, $pieces, $pieceService): RedirectResponse {
            $transfer = Transfer::query()->create([
                'from_location_id' => $fromLocation->id,
                'to_location_id' => $toLocation->id,
                'status' => 'in_transit',
                'created_by' => $request->user()->id,
                'sent_at' => now(),
            ]);

            $transfer->pieces()->attach($pieces->pluck('id'));

            foreach ($pieces as $piece) {
                $pieceService->changeStatus(
                    $piece,
                    'transfer_out',
                    'in_transit',
                    $toLocation,
                    $transfer->reference,
                    $request->user(),
                );
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "{$transfer->reference} created with {$pieces->count()} pieces",
            ]);

            return to_route('transfers.index');
        });
    }

    public function receive(Request $request, Transfer $transfer, PieceService $pieceService): RedirectResponse
    {
        if ($transfer->status !== 'in_transit') {
            return back()->withErrors(['transfer' => 'This transfer has already been received.']);
        }

        return DB::transaction(function () use ($request, $transfer, $pieceService): RedirectResponse {
            $transfer->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            $transfer->load(['pieces', 'toLocation']);

            foreach ($transfer->pieces as $piece) {
                $pieceService->changeStatus(
                    $piece,
                    'transfer_in',
                    'in_stock',
                    $transfer->toLocation,
                    $transfer->reference,
                    $request->user(),
                );
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => "{$transfer->reference} received at {$transfer->toLocation->name}",
            ]);

            return to_route('transfers.index');
        });
    }
}
