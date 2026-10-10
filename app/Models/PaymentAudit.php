<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'customer_id', 'payment_id', 'receipt_number', 'event', 'created_at'])]
class PaymentAudit extends Model
{
    public $timestamps = false;
}
