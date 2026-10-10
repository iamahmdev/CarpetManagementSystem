<?php

namespace App\Services;

use App\Models\Carpet;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PieceService
{
    public const MOVEMENT_TYPES = ['receive', 'transfer_out', 'transfer_in', 'sale', 'sale_void'];

    /**
     * @return Collection<int, Piece>
     */
    public function receive(
        Carpet $carpet,
        Location $location,
        User $user,
        int $quantity,
        string $grade,
        int $landedCost,
        ?int $price = null,
        ?int $containerId = null,
    ): Collection {
        if ($quantity < 1 || ! in_array($grade, ['A', 'B'], true) || $landedCost < 0 || ($price !== null && $price < 0)) {
            throw new InvalidArgumentException('Piece receiving data is invalid.');
        }

        return DB::transaction(function () use ($carpet, $location, $user, $quantity, $grade, $landedCost, $price, $containerId): Collection {
            $pieces = collect();
            for ($index = 0; $index < $quantity; $index++) {
                $piece = Piece::query()->create([
                    'carpet_id' => $carpet->id,
                    'container_id' => $containerId,
                    'location_id' => $location->id,
                    'status' => 'in_stock',
                    'grade' => $grade,
                    'landed_cost' => $landedCost,
                    'price' => $price,
                    'received_at' => now(),
                ]);
                $piece->update(['code' => $piece->code]);
                $this->recordMovement($piece, 'receive', null, $location, null, $user);
                $pieces->push($piece->fresh(['carpet', 'location']));
            }

            return $pieces;
        });
    }

    public function changeStatus(
        Piece $piece,
        string $type,
        string $status,
        Location $location,
        ?string $reference,
        User $user,
    ): Piece {
        if (! in_array($type, self::MOVEMENT_TYPES, true) || ! in_array($status, Piece::STATUSES, true)) {
            throw new InvalidArgumentException('Piece movement type or status is invalid.');
        }

        return DB::transaction(function () use ($piece, $type, $status, $location, $reference, $user): Piece {
            $lockedPiece = Piece::query()->lockForUpdate()->findOrFail($piece->id);
            $fromLocation = $lockedPiece->location;
            $lockedPiece->update([
                'location_id' => $location->id,
                'status' => $status,
            ]);
            $this->recordMovement($lockedPiece, $type, $fromLocation, $location, $reference, $user);

            return $lockedPiece->fresh(['carpet', 'location']);
        });
    }

    private function recordMovement(
        Piece $piece,
        string $type,
        ?Location $fromLocation,
        ?Location $toLocation,
        ?string $reference,
        User $user,
    ): void {
        PieceMovement::query()->create([
            'piece_id' => $piece->id,
            'type' => $type,
            'from_location_id' => $fromLocation?->id,
            'to_location_id' => $toLocation?->id,
            'reference' => $reference,
            'user_id' => $user->id,
            'moved_at' => now(),
        ]);
    }
}
