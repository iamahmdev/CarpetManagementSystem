<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Carpet extends Model
{
    protected $fillable = [
        'sku',
        'name',
        'design',
        'weave',
        'material',
        'color',
        'size',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
        ];
    }

    public function containerItems(): HasMany
    {
        return $this->hasMany(ContainerItem::class);
    }
}
