<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarpetRequest;
use App\Models\Carpet;
use App\Models\Piece;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CarpetController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('carpets/index', [
            'carpets' => Carpet::query()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (Carpet $carpet): array => [
                    'id' => $carpet->id,
                    'sku' => $carpet->sku,
                    'name' => $carpet->name,
                    'design' => $carpet->design,
                    'weave' => $carpet->weave,
                    'size' => $carpet->size,
                    'color' => $carpet->color,
                    'in_stock_count' => Piece::query()
                        ->where('carpet_id', $carpet->id)
                        ->where('status', 'in_stock')
                        ->count(),
                    'price' => $carpet->price,
                ]),
            'canManage' => in_array(
                'catalog.manage',
                config('roles.'.$request->user()->role->value, []),
                true,
            ),
        ]);
    }

    public function store(StoreCarpetRequest $request): RedirectResponse
    {
        $carpet = Carpet::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$carpet->name} added to the catalog",
        ]);

        return to_route('carpets.index');
    }
}
