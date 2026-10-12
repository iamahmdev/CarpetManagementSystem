<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\Piece;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $canManageContainers = in_array(
            'containers.manage',
            config('roles.'.$request->user()->role->value, []),
            true,
        );
        $imports = $canManageContainers
            ? Container::query()
                ->with(['destination', 'items'])
                ->orderByDesc('eta')
                ->limit(3)
                ->get()
                ->map(fn (Container $container): array => [
                    'id' => $container->id,
                    'number' => $container->number,
                    'destination' => $container->destination->name,
                    'expected_pieces' => $container->items->sum('expected_quantity'),
                    'status' => $container->status,
                ])
                ->all()
            : [];

        $piecesInTransit = Piece::query()->where('status', 'in_transit')->count();
        $transfersPending = Transfer::query()->where('status', 'in_transit')->count();

        return Inertia::render('dashboard', [
            'can_manage_containers' => $canManageContainers,
            'imports' => $imports,
            'pieces_in_transit' => $piecesInTransit,
            'transfers_pending' => $transfersPending,
        ]);
    }
}
