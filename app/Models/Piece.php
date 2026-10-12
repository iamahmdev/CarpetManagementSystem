<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'code',
    'carpet_id',
    'container_id',
    'container_number',
    'location_id',
    'status',
    'grade',
    'landed_cost',
    'price',
    'received_at',
])]
class Piece extends Model
{
    public const STATUSES = ['in_stock', 'in_transit', 'sold', 'damaged', 'reserved'];

    protected function casts(): array
    {
        return [
            'landed_cost' => 'integer',
            'price' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function getCodeAttribute(?string $value): string
    {
        return $value ?? 'CP-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public static function normalizeCode(string $code): string
    {
        $code = Str::upper(trim($code));
        if (preg_match('/^(?:CP-)?(\d{1,6})$/', $code, $matches) === 1) {
            return 'CP-'.str_pad($matches[1], 6, '0', STR_PAD_LEFT);
        }

        return $code;
    }

    public function carpet(): BelongsTo
    {
        return $this->belongsTo(Carpet::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PieceMovement::class);
    }

    public function transfers(): BelongsToMany
    {
        return $this->belongsToMany(Transfer::class, 'transfer_pieces');
    }

    public function saleItem(): HasOne
    {
        return $this->hasOne(SaleItem::class);
    }

    public function effectivePrice(): int
    {
        return $this->price ?? $this->carpet->price;
    }

    public function displayName(): string
    {
        return $this->carpet->name.' '.$this->carpet->size;
    }
}
