<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLocationRequest;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('locations/index', [
            'locations' => Location::query()
                ->with('adjacentWarehouse:id,name')
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->map(fn (Location $location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'type' => $location->type,
                    'city' => $location->city,
                    'adjacent_warehouse' => $location->adjacentWarehouse?->name,
                    'pieces_count' => 0,
                ]),
            'warehouses' => Location::warehouses()
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['parent_id'] = $data['type'] === 'warehouse'
            ? null
            : ($data['parent_id'] ?? null);

        $location = Location::create([
            ...$data,
            'active' => true,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$location->name} added. It is available everywhere straight away.",
        ]);

        return to_route('locations.index');
    }
}
