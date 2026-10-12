<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'reference',
    'from_location_id',
    'to_location_id',
    'status',
    'created_by',
    'sent_at',
    'received_at',
])]
class Transfer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Transfer $transfer): void {
            if (! $transfer->reference) {
                $transfer->updateQuietly([
                    'reference' => sprintf('TR-%04d', $transfer->id),
                ]);
            }
        });
    }

    public function getReferenceAttribute(?string $value): string
    {
        return $value ?? sprintf('TR-%04d', $this->id);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(Piece::class, 'transfer_pieces');
    }
}
