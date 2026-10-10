<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales') || ! Schema::hasColumn('sales', 'payments')) {
            return;
        }

        DB::table('sales')
            ->whereNotNull('payments')
            ->orderBy('id')
            ->chunkById(100, function ($sales): void {
                foreach ($sales as $sale) {
                    $legacyPayments = is_array($sale->payments)
                        ? $sale->payments
                        : json_decode($sale->payments, true, flags: JSON_THROW_ON_ERROR);

                    foreach ($legacyPayments as $index => $legacyPayment) {
                        $legacyKey = $sale->id.':'.$index;
                        if (DB::table('payments')->where('legacy_key', $legacyKey)->exists()) {
                            continue;
                        }
                        $amount = (int) ($legacyPayment['amount'] ?? 0);
                        if ($amount <= 0) {
                            continue;
                        }

                        $receivedAt = $legacyPayment['at'] ?? $sale->sold_at ?? now();
                        $year = (int) date('Y', strtotime($receivedAt));
                        DB::table('document_sequences')->insertOrIgnore([
                            'document_type' => 'receipt',
                            'year' => $year,
                            'last_number' => 0,
                        ]);
                        $sequence = DB::table('document_sequences')
                            ->where('document_type', 'receipt')
                            ->where('year', $year)
                            ->lockForUpdate()
                            ->first();
                        $number = $sequence->last_number + 1;
                        DB::table('document_sequences')
                            ->where('document_type', 'receipt')
                            ->where('year', $year)
                            ->update(['last_number' => $number]);
                        $receipt = sprintf('RCP-%d-%04d', $year, $number);
                        $paymentId = DB::table('payments')->insertGetId([
                            'receipt_number' => $receipt,
                            'legacy_key' => $legacyKey,
                            'sale_id' => $sale->id,
                            'customer_id' => $sale->customer_id,
                            'amount' => $amount,
                            'method' => $legacyPayment['method'] ?? 'cash',
                            'received_at' => $receivedAt,
                            'received_by' => $sale->created_by,
                            'status' => 'active',
                            'created_at' => $receivedAt,
                            'updated_at' => $receivedAt,
                        ]);
                        DB::table('payment_allocations')->insert([
                            'payment_id' => $paymentId,
                            'sale_id' => $sale->id,
                            'amount' => $amount,
                            'created_at' => $receivedAt,
                            'updated_at' => $receivedAt,
                        ]);
                        DB::table('payment_audits')->insert([
                            'user_id' => $sale->created_by,
                            'customer_id' => $sale->customer_id,
                            'payment_id' => $paymentId,
                            'receipt_number' => $receipt,
                            'event' => 'payment_migrated',
                            'created_at' => $receivedAt,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Payments are retained to avoid deleting financial history during rollback.
    }
};
