<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_id', 'piece_id', 'carpet_id', 'sku', 'name', 'unit_price'])]
class SaleItem extends Model
{
    protected function casts(): array
    {
        return ['unit_price' => 'integer'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function carpet(): BelongsTo
    {
        return $this->belongsTo(Carpet::class);
    }

    public function piece(): BelongsTo
    {
        return $this->belongsTo(Piece::class);
    }
}
