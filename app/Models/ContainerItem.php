<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['container_id', 'carpet_id', 'expected_quantity', 'unit_price', 'received_quantity'])]
class ContainerItem extends Model
{
    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'unit_price' => 'integer',
            'received_quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Container, $this> */
    public function container(): BelongsTo
    {
        return $this->belongsTo(Container::class);
    }

    /** @return BelongsTo<Carpet, $this> */
    public function carpet(): BelongsTo
    {
        return $this->belongsTo(Carpet::class);
    }
}
