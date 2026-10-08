<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
