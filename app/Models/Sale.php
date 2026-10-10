<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'invoice_number',
    'customer_id',
    'location_id',
    'created_by',
    'status',
    'subtotal',
    'discount',
    'total',
    'sold_at',
])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'sold_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activePayments(): HasMany
    {
        return $this->payments()->where('status', 'active');
    }

    public function paidAmount(): int
    {
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments
                ->where('status', 'active')
                ->sum('amount');
        }

        return (int) $this->activePayments()->sum('amount');
    }

    public function balanceDue(): int
    {
        return $this->status === 'void' ? 0 : max(0, $this->total - $this->paidAmount());
    }

    public function paymentStatus(): string
    {
        if ($this->status === 'void') {
            return 'Void';
        }

        $paid = $this->paidAmount();

        return $paid >= $this->total ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid');
    }
}
