<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentAudit;
use App\Models\Sale;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public const METHODS = ['cash', 'bank_transfer', 'cheque', 'mobile_wallet'];

    public function recordForSale(
        Sale $sale,
        int $amount,
        string $method,
        DateTimeInterface $receivedAt,
        User $user,
        ?string $note = null,
    ): string {
        return DB::transaction(function () use ($sale, $amount, $method, $receivedAt, $user, $note): string {
            $lockedSale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($lockedSale->status === 'void' || $amount > $lockedSale->balanceDue()) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot exceed the invoice balance.',
                ]);
            }

            return $this->recordAllocations(
                customer: $lockedSale->customer,
                allocations: [$lockedSale->id => $amount],
                method: $method,
                receivedAt: $receivedAt,
                user: $user,
                note: $note,
            );
        });
    }

    /**
     * @param  array<int|string, int>  $allocations
     */
    public function recordForCustomer(
        Customer $customer,
        int $amount,
        array $allocations,
        string $method,
        DateTimeInterface $receivedAt,
        User $user,
        ?string $note = null,
    ): string {
        return DB::transaction(function () use ($customer, $amount, $allocations, $method, $receivedAt, $user, $note): string {
            $openSales = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->orderBy('sold_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $balances = $openSales->mapWithKeys(fn (Sale $sale): array => [
                $sale->id => $sale->balanceDue(),
            ])->filter(fn (int $balance): bool => $balance > 0);

            $totalBalance = (int) $balances->sum();

            if ($amount > $totalBalance) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot exceed the customer balance.',
                ]);
            }

            if ($allocations === []) {
                $remaining = $amount;
                foreach ($balances as $saleId => $balance) {
                    $allocation = min($remaining, $balance);
                    if ($allocation > 0) {
                        $allocations[$saleId] = $allocation;
                        $remaining -= $allocation;
                    }
                    if ($remaining === 0) {
                        break;
                    }
                }
            }

            $normalized = [];
            foreach ($allocations as $saleId => $allocation) {
                $saleId = (int) $saleId;
                $allocation = (int) $allocation;
                if ($allocation > 0) {
                    if (! $balances->has($saleId) || $allocation > $balances->get($saleId)) {
                        throw ValidationException::withMessages([
                            'allocations' => 'An allocation exceeds an open invoice balance.',
                        ]);
                    }
                    $normalized[$saleId] = $allocation;
                }
            }

            if (array_sum($normalized) !== $amount) {
                throw ValidationException::withMessages([
                    'allocations' => 'The invoice allocations must equal the payment amount.',
                ]);
            }

            return $this->recordAllocations($customer, $normalized, $method, $receivedAt, $user, $note);
        });
    }

    /**
     * @param  array<int|string, int>  $allocations
     */
    private function recordAllocations(
        ?Customer $customer,
        array $allocations,
        string $method,
        DateTimeInterface $receivedAt,
        User $user,
        ?string $note,
    ): string {
        $receiptNumber = $this->nextReceiptNumber((int) $receivedAt->format('Y'));
        $balanceAfter = $customer
            ? max(0, (int) Sale::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'completed')
                ->get()
                ->sum(fn (Sale $sale): int => $sale->balanceDue()) - array_sum($allocations))
            : null;

        foreach ($allocations as $saleId => $amount) {
            $sale = Sale::query()->findOrFail((int) $saleId);
            $payment = Payment::query()->create([
                'receipt_number' => $receiptNumber,
                'sale_id' => $sale->id,
                'customer_id' => $customer?->id,
                'amount' => $amount,
                'customer_balance_after' => $balanceAfter,
                'method' => $method,
                'received_at' => $receivedAt,
                'received_by' => $user->id,
                'note' => $note,
                'status' => 'active',
            ]);
            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'sale_id' => $sale->id,
                'amount' => $amount,
            ]);
            PaymentAudit::query()->create([
                'user_id' => $user->id,
                'customer_id' => $customer?->id,
                'payment_id' => $payment->id,
                'receipt_number' => $receiptNumber,
                'event' => 'payment_recorded',
                'created_at' => now(),
            ]);
        }

        return $receiptNumber;
    }

    public function reverseReceipt(string $receiptNumber, string $reason, User $user): void
    {
        DB::transaction(function () use ($receiptNumber, $reason, $user): void {
            $payments = Payment::query()
                ->where('receipt_number', $receiptNumber)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($payments->isEmpty()) {
                throw ValidationException::withMessages([
                    'receipt' => 'This receipt has already been reversed or does not exist.',
                ]);
            }

            foreach ($payments as $payment) {
                $payment->update([
                    'status' => 'reversed',
                    'reversed_at' => now(),
                    'reversed_by' => $user->id,
                    'reversal_reason' => $reason,
                ]);
                PaymentAudit::query()->create([
                    'user_id' => $user->id,
                    'customer_id' => $payment->customer_id,
                    'payment_id' => $payment->id,
                    'receipt_number' => $receiptNumber,
                    'event' => 'payment_reversed',
                    'created_at' => now(),
                ]);
            }
        });
    }

    private function nextReceiptNumber(int $year): string
    {
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

        return sprintf('RCP-%d-%04d', $year, $number);
    }
}
