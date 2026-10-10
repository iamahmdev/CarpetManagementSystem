<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Location|null $adjacentWarehouse
 */
class Location extends Model
{
    protected $fillable = [
        'name',
        'type',
        'city',
        'parent_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWarehouses(Builder $query): Builder
    {
        return $query->where('type', 'warehouse');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDisplays(Builder $query): Builder
    {
        return $query->where('type', 'display');
    }

    /** @return BelongsTo<Location, $this> */
    public function adjacentWarehouse(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Container, $this> */
    public function containers(): HasMany
    {
        return $this->hasMany(Container::class, 'destination_id');
    }
}
