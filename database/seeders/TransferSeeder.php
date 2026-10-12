<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Piece;
use App\Models\Transfer;
use App\Models\User;
use App\Services\PieceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::query()->whereIn('role', ['owner', 'admin'])->first() ?? User::query()->first();
            if (! $user) {
                return;
            }

            $kohatWarehouse = Location::query()->where('name', 'Kohat Warehouse')->first();
            $kohatDisplay = Location::query()->where('name', 'Kohat Display')->first();
            $hanguWarehouse = Location::query()->where('name', 'Hangu Warehouse')->first();
            $hanguDisplay = Location::query()->where('name', 'Hangu Display')->first();

            if (! $kohatWarehouse || ! $kohatDisplay || ! $hanguWarehouse || ! $hanguDisplay) {
                return;
            }

            $pieceService = app(PieceService::class);

            // TR-0001: Kohat Warehouse to Kohat Display, 10 pieces, received (27 Aug)
            if (! Transfer::query()->where('reference', 'TR-0001')->exists()) {
                $kohatPieces = Piece::query()
                    ->where('location_id', $kohatWarehouse->id)
                    ->where('status', 'in_stock')
                    ->whereRaw('id % 2 = 0')
                    ->limit(10)
                    ->get();

                if ($kohatPieces->count() < 10) {
                    $kohatPieces = Piece::query()
                        ->where('location_id', $kohatWarehouse->id)
                        ->where('status', 'in_stock')
                        ->limit(10)
                        ->get();
                }

                if ($kohatPieces->count() === 10) {
                    $sentAt = Carbon::create(2026, 8, 27, 9, 30, 0);
                    $receivedAt = Carbon::create(2026, 8, 27, 14, 0, 0);

                    $tr1 = Transfer::query()->create([
                        'reference' => 'TR-0001',
                        'from_location_id' => $kohatWarehouse->id,
                        'to_location_id' => $kohatDisplay->id,
                        'status' => 'received',
                        'created_by' => $user->id,
                        'sent_at' => $sentAt,
                        'received_at' => $receivedAt,
                    ]);

                    $tr1->pieces()->attach($kohatPieces->pluck('id'));

                    foreach ($kohatPieces as $piece) {
                        $pieceService->changeStatus(
                            $piece,
                            'transfer_out',
                            'in_transit',
                            $kohatDisplay,
                            'TR-0001',
                            $user,
                            $sentAt,
                        );
                        $pieceService->changeStatus(
                            $piece,
                            'transfer_in',
                            'in_stock',
                            $kohatDisplay,
                            'TR-0001',
                            $user,
                            $receivedAt,
                        );
                    }
                }
            }

            // TR-0002: Hangu Warehouse to Hangu Display, 11 pieces, received (6 Sept)
            if (! Transfer::query()->where('reference', 'TR-0002')->exists()) {
                $hanguPieces = Piece::query()
                    ->where('location_id', $hanguWarehouse->id)
                    ->where('status', 'in_stock')
                    ->whereRaw('id % 2 = 0')
                    ->limit(11)
                    ->get();

                if ($hanguPieces->count() < 11) {
                    $hanguPieces = Piece::query()
                        ->where('location_id', $hanguWarehouse->id)
                        ->where('status', 'in_stock')
                        ->limit(11)
                        ->get();
                }

                if ($hanguPieces->count() === 11) {
                    $sentAt = Carbon::create(2026, 9, 6, 10, 15, 0);
                    $receivedAt = Carbon::create(2026, 9, 6, 16, 45, 0);

                    $tr2 = Transfer::query()->create([
                        'reference' => 'TR-0002',
                        'from_location_id' => $hanguWarehouse->id,
                        'to_location_id' => $hanguDisplay->id,
                        'status' => 'received',
                        'created_by' => $user->id,
                        'sent_at' => $sentAt,
                        'received_at' => $receivedAt,
                    ]);

                    $tr2->pieces()->attach($hanguPieces->pluck('id'));

                    foreach ($hanguPieces as $piece) {
                        $pieceService->changeStatus(
                            $piece,
                            'transfer_out',
                            'in_transit',
                            $hanguDisplay,
                            'TR-0002',
                            $user,
                            $sentAt,
                        );
                        $pieceService->changeStatus(
                            $piece,
                            'transfer_in',
                            'in_stock',
                            $hanguDisplay,
                            'TR-0002',
                            $user,
                            $receivedAt,
                        );
                    }
                }
            }

            // TR-0003: Hangu Warehouse to Hangu Display, 3 pieces, in transit (sent today at 9:15 AM)
            if (! Transfer::query()->where('reference', 'TR-0003')->exists()) {
                $transitPieces = Piece::query()
                    ->where('location_id', $hanguWarehouse->id)
                    ->where('status', 'in_stock')
                    ->whereIn('id', [3621, 3623, 3625])
                    ->get();

                if ($transitPieces->count() < 3) {
                    $transitPieces = Piece::query()
                        ->where('location_id', $hanguWarehouse->id)
                        ->where('status', 'in_stock')
                        ->limit(3)
                        ->get();
                }

                if ($transitPieces->count() === 3) {
                    $sentAt = now()->setTime(9, 15, 0);

                    $tr3 = Transfer::query()->create([
                        'reference' => 'TR-0003',
                        'from_location_id' => $hanguWarehouse->id,
                        'to_location_id' => $hanguDisplay->id,
                        'status' => 'in_transit',
                        'created_by' => $user->id,
                        'sent_at' => $sentAt,
                        'received_at' => null,
                    ]);

                    $tr3->pieces()->attach($transitPieces->pluck('id'));

                    foreach ($transitPieces as $piece) {
                        $pieceService->changeStatus(
                            $piece,
                            'transfer_out',
                            'in_transit',
                            $hanguDisplay,
                            'TR-0003',
                            $user,
                            $sentAt,
                        );
                    }
                }
            }
        });
    }
}
