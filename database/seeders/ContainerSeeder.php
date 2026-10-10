<?php

namespace Database\Seeders;

use App\Models\Carpet;
use App\Models\Container;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\User;
use App\Services\PieceService;
use App\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ContainerSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $locations = Location::query()
                ->whereIn('name', ['Kohat Warehouse', 'Hangu Warehouse'])
                ->get()
                ->keyBy('name');
            $carpets = Carpet::query()->get()->keyBy('name');
            $admin = User::query()->where('role', UserRole::ADMIN->value)->first()
                ?? User::query()->create([
                    'name' => 'Admin',
                    'email' => 'admin@example.test',
                    'password' => Hash::make(Str::random(64)),
                    'role' => UserRole::ADMIN,
                ]);

            $specifications = [
                [
                    'number' => 'IR-2026-012',
                    'supplier' => 'Tabriz Carpet Exports',
                    'destination' => 'Kohat Warehouse',
                    'eta_days' => -54,
                    'arrived_days' => -52,
                    'status' => 'received',
                    'freight_cost' => 210000,
                    'customs_duty_cost' => 130000,
                    'clearing_cost' => 40000,
                    'transport_cost' => 40000,
                    'items' => [
                        ['Tabriz Herati', 6, 90000],
                        ['Kashan Medallion', 8, 45000],
                        ['Nain Fine Weave', 6, 30000],
                    ],
                ],
                [
                    'number' => 'IR-2026-013',
                    'supplier' => 'Isfahan Weavers Co.',
                    'destination' => 'Hangu Warehouse',
                    'eta_days' => -44,
                    'arrived_days' => -42,
                    'status' => 'received',
                    'freight_cost' => 230000,
                    'customs_duty_cost' => 210000,
                    'clearing_cost' => 60000,
                    'transport_cost' => 45000,
                    'items' => [
                        ['Qom Garden', 6, 70000],
                        ['Isfahan Shah Abbasi', 4, 150000],
                        ['Bakhtiari Garden', 8, 62000],
                        ['Mashhad Classic', 3, 130000],
                    ],
                ],
                [
                    'number' => 'IR-2026-014',
                    'supplier' => 'Tabriz Carpet Exports',
                    'destination' => 'Kohat Warehouse',
                    'eta_days' => -3,
                    'arrived_days' => -2,
                    'status' => 'arrived',
                    'freight_cost' => 250000,
                    'customs_duty_cost' => 290000,
                    'clearing_cost' => 55000,
                    'transport_cost' => 45000,
                    'items' => [
                        ['Tabriz Herati', 8, 90000],
                        ['Isfahan Shah Abbasi', 4, 150000],
                        ['Mashhad Classic', 4, 130000],
                        ['Modern Machine Made', 12, 15000],
                    ],
                ],
                [
                    'number' => 'IR-2026-015',
                    'supplier' => 'Kashan Handloom',
                    'destination' => 'Hangu Warehouse',
                    'eta_days' => 12,
                    'arrived_days' => null,
                    'status' => 'on_the_way',
                    'freight_cost' => 190000,
                    'customs_duty_cost' => 0,
                    'clearing_cost' => 0,
                    'transport_cost' => 0,
                    'items' => [
                        ['Kashan Medallion', 10, 45000],
                        ['Qom Garden', 6, 70000],
                        ['Nain Fine Weave', 10, 30000],
                    ],
                ],
            ];

            foreach ($specifications as $specification) {
                $arrivedAt = $specification['arrived_days'] === null
                    ? null
                    : now()->addDays($specification['arrived_days']);
                $container = Container::query()->updateOrCreate(
                    ['number' => $specification['number']],
                    [
                        'supplier' => $specification['supplier'],
                        'destination_id' => $locations
                            ->get($specification['destination'])
                            ?->id ?? Location::query()
                            ->where('name', $specification['destination'])
                            ->firstOrFail()
                            ->id,
                        'status' => $specification['status'],
                        'eta' => now()->addDays($specification['eta_days'])->toDateString(),
                        'arrived_at' => $arrivedAt,
                        'freight_cost' => $specification['freight_cost'],
                        'customs_duty_cost' => $specification['customs_duty_cost'],
                        'clearing_cost' => $specification['clearing_cost'],
                        'transport_cost' => $specification['transport_cost'],
                    ],
                );

                foreach ($specification['items'] as [$carpetName, $quantity, $unitPrice]) {
                    $carpet = $carpets->get($carpetName)
                        ?? Carpet::query()->where('name', $carpetName)->firstOrFail();
                    $container->items()->updateOrCreate(
                        ['carpet_id' => $carpet->id],
                        [
                            'expected_quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'received_quantity' => $specification['status'] === 'received'
                                ? $quantity
                                : 0,
                        ],
                    );
                }

                if ($specification['status'] !== 'received' || $container->pieces()->exists()) {
                    continue;
                }

                $goodsCost = (int) $container->items()
                    ->selectRaw('SUM(expected_quantity * unit_price) as goods_cost')
                    ->value('goods_cost');
                $receivedAt = now()->addDays($specification['arrived_days']);
                $pieceIds = collect();

                foreach ($container->items()->with('carpet')->orderBy('id')->get() as $item) {
                    $created = app(PieceService::class)->receive(
                        $item->carpet,
                        $container->destination,
                        $admin,
                        $item->expected_quantity,
                        $item->unit_price,
                        $goodsCost,
                        $container->freight_cost,
                        $container->customs_duty_cost,
                        $container->clearing_cost,
                        $container->transport_cost,
                        $container->number,
                        $container->id,
                    );
                    $pieceIds = $pieceIds->merge($created->pluck('id'));
                }

                Piece::query()->whereIn('id', $pieceIds)->update([
                    'received_at' => $receivedAt,
                ]);
                PieceMovement::query()
                    ->whereIn('piece_id', $pieceIds)
                    ->where('type', 'receive')
                    ->update(['moved_at' => $receivedAt]);
            }
        });
    }
}
