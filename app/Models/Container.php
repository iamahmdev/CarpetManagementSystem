<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'number',
    'supplier',
    'destination_id',
    'status',
    'eta',
    'arrived_at',
    'freight_cost',
    'customs_duty_cost',
    'clearing_cost',
    'transport_cost',
])]
class Container extends Model
{
    public const STATUSES = ['on_the_way', 'arrived', 'received'];

    protected function casts(): array
    {
        return [
            'eta' => 'date',
            'arrived_at' => 'datetime',
            'freight_cost' => 'integer',
            'customs_duty_cost' => 'integer',
            'clearing_cost' => 'integer',
            'transport_cost' => 'integer',
        ];
    }

    /** @return BelongsTo<Location, $this> */
    public function destination(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_id');
    }

    /** @return HasMany<ContainerItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ContainerItem::class);
    }

    /** @return HasMany<Piece, $this> */
    public function pieces(): HasMany
    {
        return $this->hasMany(Piece::class);
    }

    public function goodsCost(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items->sum(
                fn (ContainerItem $item): int => $item->expected_quantity * $item->unit_price,
            );
        }

        return (int) $this->items()
            ->selectRaw('SUM(expected_quantity * unit_price) as goods_cost')
            ->value('goods_cost');
    }

    public function totalLandedCost(): int
    {
        return $this->goodsCost()
            + $this->freight_cost
            + $this->customs_duty_cost
            + $this->clearing_cost
            + $this->transport_cost;
    }
}
