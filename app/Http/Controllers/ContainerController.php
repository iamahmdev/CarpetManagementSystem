<?php

namespace App\Http\Controllers;

use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Piece;
use App\Services\PieceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ContainerController extends Controller
{
    public function index(Request $request): Response
    {
        $canViewCosts = in_array(
            'costs.view',
            config('roles.'.$request->user()->role->value, []),
            true,
        );
        $containers = Container::query()
            ->with(['destination', 'items'])
            ->orderByDesc('eta')
            ->get();
        $selectedContainer = $request->filled('container')
            ? $containers->firstWhere('id', $request->integer('container'))
            : null;
        $showLabels = $request->boolean('labels');

        if ($showLabels && $selectedContainer?->status !== 'received') {
            abort(422, 'Labels are available only after a container is received.');
        }

        $selectedData = $selectedContainer
            ? $this->containerDetails($selectedContainer, $canViewCosts)
            : null;
        $labelPieces = $showLabels
            ? Piece::query()
                ->with('carpet')
                ->where('container_id', $selectedContainer->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Piece $piece): array => [
                    'code' => $piece->code,
                    'name' => $piece->displayName(),
                ])
                ->all()
            : [];

        return Inertia::render('containers/index', [
            'containers' => $containers->map(fn (Container $container): array => [
                'id' => $container->id,
                'number' => $container->number,
                'supplier' => $container->supplier,
                'destination' => $container->destination->name,
                'eta_text' => $container->arrived_at
                    ? $container->arrived_at->format('j M')
                    : 'Due '.$container->eta->format('j M'),
                'expected_pieces' => $container->items->sum('expected_quantity'),
                'status' => $container->status,
            ])->all(),
            'selected_container' => $selectedData,
            'show_labels' => $showLabels,
            'label_pieces' => $labelPieces,
            'label_count' => count($labelPieces),
            'can_view_costs' => $canViewCosts,
            'success_message' => session('success_message'),
        ]);
    }

    public function markArrived(Container $container): RedirectResponse
    {
        DB::transaction(function () use ($container): void {
            $lockedContainer = Container::query()
                ->lockForUpdate()
                ->findOrFail($container->id);
            abort_unless(
                $lockedContainer->status === 'on_the_way',
                422,
                'Only containers on the way can be marked as arrived.',
            );
            $lockedContainer->update([
                'status' => 'arrived',
                'arrived_at' => now(),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$container->number} marked as arrived",
        ]);

        return to_route('containers.index');
    }

    public function receive(Request $request, Container $container, PieceService $pieces): RedirectResponse
    {
        $data = $request->validate([
            'quantities' => ['required', 'array'],
        ]);

        $createdPiecesCount = DB::transaction(function () use ($container, $data, $request, $pieces): int {
            $lockedContainer = Container::query()
                ->lockForUpdate()
                ->with(['items.carpet'])
                ->findOrFail($container->id);
            abort_unless(
                $lockedContainer->status === 'arrived',
                422,
                'Only arrived containers can be received.',
            );

            $goodsCost = $lockedContainer->goodsCost();
            $createdPiecesCount = 0;

            foreach ($lockedContainer->items as $item) {
                $receivedQuantity = $this->receivedQuantity(
                    $data['quantities'][(string) $item->id] ?? null,
                    $item->expected_quantity,
                );
                $item->update(['received_quantity' => $receivedQuantity]);

                if ($receivedQuantity === 0) {
                    continue;
                }

                $createdPieces = $pieces->receive(
                    $item->carpet,
                    $lockedContainer->destination()->firstOrFail(),
                    $request->user(),
                    $receivedQuantity,
                    $item->unit_price,
                    $goodsCost,
                    $lockedContainer->freight_cost,
                    $lockedContainer->customs_duty_cost,
                    $lockedContainer->clearing_cost,
                    $lockedContainer->transport_cost,
                    $lockedContainer->number,
                    $lockedContainer->id,
                );
                $createdPiecesCount += $createdPieces->count();
            }

            $lockedContainer->update([
                'status' => 'received',
                'arrived_at' => $lockedContainer->arrived_at ?? now(),
            ]);

            return $createdPiecesCount;
        });

        $destination = $container->destination()->firstOrFail()->name;
        $message = "{$createdPiecesCount} pieces received into {$destination}";
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('containers.index', [
            'container' => $container->id,
            'labels' => 1,
        ])->with('success_message', $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function containerDetails(Container $container, bool $canViewCosts): array
    {
        $container->loadMissing(['destination', 'items.carpet']);
        $details = [
            'id' => $container->id,
            'number' => $container->number,
            'supplier' => $container->supplier,
            'destination' => $container->destination->name,
            'status' => $container->status,
            'arrival_text' => $container->arrived_at
                ? $container->arrived_at->format('j M')
                : 'Expected '.$container->eta->format('j M'),
            'items' => $container->items->map(fn (ContainerItem $item): array => [
                'id' => $item->id,
                'carpet' => $item->carpet->name.' '.$item->carpet->size,
                'expected_quantity' => $item->expected_quantity,
                'received_quantity' => $item->received_quantity,
                ...($canViewCosts ? [
                    'unit_price' => $item->unit_price,
                    'landed_cost_each' => $this->landedCostEach($container, $item),
                ] : []),
            ])->all(),
        ];

        if ($canViewCosts) {
            $goodsCost = $container->goodsCost();
            $details['costs'] = [
                ['label' => 'Goods (Iran)', 'amount' => $goodsCost],
                ['label' => 'Sea and land freight', 'amount' => $container->freight_cost],
                ['label' => 'Customs duty', 'amount' => $container->customs_duty_cost],
                ['label' => 'Clearing agent', 'amount' => $container->clearing_cost],
                ['label' => 'Transport to warehouse', 'amount' => $container->transport_cost],
            ];
            $details['total_landed_cost'] = $container->totalLandedCost();
        }

        return $details;
    }

    private function landedCostEach(Container $container, ContainerItem $item): int
    {
        $goodsCost = $container->goodsCost();
        if ($goodsCost === 0) {
            return $item->unit_price;
        }

        return (int) round($item->unit_price * $container->totalLandedCost() / $goodsCost);
    }

    private function receivedQuantity(mixed $quantity, int $expected): int
    {
        if (! is_scalar($quantity) || filter_var((string) $quantity, FILTER_VALIDATE_INT) === false) {
            return 0;
        }

        return min($expected, max(0, (int) $quantity));
    }
}
