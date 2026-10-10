<?php

namespace App\Services;

use App\Models\Carpet;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\User;
use DateTimeInterface;
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
        int $unitPrice,
        int $goodsCost,
        int $freightCost,
        int $customsDuty,
        int $clearingCost,
        int $transportCost,
        ?string $containerNumber = null,
        ?int $containerId = null,
    ): Collection {
        if (
            $quantity < 1
            || min($unitPrice, $goodsCost, $freightCost, $customsDuty, $clearingCost, $transportCost) < 0
        ) {
            throw new InvalidArgumentException('Piece receiving data is invalid.');
        }

        return DB::transaction(function () use (
            $carpet,
            $location,
            $user,
            $quantity,
            $unitPrice,
            $goodsCost,
            $freightCost,
            $customsDuty,
            $clearingCost,
            $transportCost,
            $containerNumber,
            $containerId,
        ): Collection {
            $pieces = collect();
            $landedCost = $goodsCost + $freightCost + $customsDuty + $clearingCost + $transportCost;
            $multiplier = $goodsCost === 0 ? 1 : $landedCost / $goodsCost;
            for ($index = 0; $index < $quantity; $index++) {
                $piece = Piece::query()->create([
                    'carpet_id' => $carpet->id,
                    'container_id' => $containerId,
                    'container_number' => $containerNumber,
                    'location_id' => $location->id,
                    'status' => 'in_stock',
                    'grade' => 'A',
                    'landed_cost' => (int) round($unitPrice * $multiplier),
                    'price' => null,
                    'received_at' => now(),
                ]);
                $piece->update([
                    'code' => $piece->code,
                    'grade' => match ($piece->id % 3) {
                        0, 1 => 'A',
                        2 => 'B',
                    },
                ]);
                $this->recordMovement($piece, 'receive', null, $location, $containerNumber, $user);
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
        ?DateTimeInterface $movedAt = null,
    ): Piece {
        if (! in_array($type, self::MOVEMENT_TYPES, true) || ! in_array($status, Piece::STATUSES, true)) {
            throw new InvalidArgumentException('Piece movement type or status is invalid.');
        }

        $expectedStatus = match ($type) {
            'receive', 'transfer_in', 'sale_void' => 'in_stock',
            'transfer_out' => 'in_transit',
            'sale' => 'sold',
        };
        if ($status !== $expectedStatus) {
            throw new InvalidArgumentException('Piece movement type and status do not match.');
        }

        return DB::transaction(function () use ($piece, $type, $status, $location, $reference, $user, $movedAt): Piece {
            $lockedPiece = Piece::query()->lockForUpdate()->findOrFail($piece->id);
            $fromLocation = in_array($type, ['receive', 'transfer_in', 'sale_void'], true)
                ? null
                : $lockedPiece->location;
            $toLocation = in_array($type, ['sale', 'transfer_out'], true)
                ? ($type === 'sale' ? null : $location)
                : $location;
            $lockedPiece->update([
                'location_id' => $type === 'transfer_out'
                    ? $lockedPiece->location_id
                    : $location->id,
                'status' => $status,
            ]);
            $this->recordMovement($lockedPiece, $type, $fromLocation, $toLocation, $reference, $user, $movedAt);

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
        ?DateTimeInterface $movedAt = null,
    ): void {
        PieceMovement::query()->create([
            'piece_id' => $piece->id,
            'type' => $type,
            'from_location_id' => $fromLocation?->id,
            'to_location_id' => $toLocation?->id,
            'reference' => $reference,
            'user_id' => $user->id,
            'moved_at' => $movedAt ?? now(),
        ]);
    }
}
