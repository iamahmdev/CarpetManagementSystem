<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentAudit;
use App\Models\Sale;
use App\Services\PaymentService;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function storeForCustomer(Request $request, Customer $customer, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(PaymentService::METHODS)],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['sometimes', 'array'],
            'allocations.*' => ['required', 'integer', 'min:0'],
        ], [
            'amount.required' => 'Enter an amount',
            'amount.min' => 'Enter an amount',
        ]);
        $receivedAt = Carbon::parse($data['received_at']);

        if ($request->user()->role === UserRole::SALES && $receivedAt->lt(now()->startOfMinute())) {
            return back()->withErrors(['received_at' => 'Only Owner and Admin can back-date payments.']);
        }

        $allocations = collect($data['allocations'] ?? [])
            ->mapWithKeys(fn (int|string $amount, string|int $saleId): array => [(int) $saleId => (int) $amount])
            ->all();
        $receiptNumber = $payments->recordForCustomer(
            $customer,
            (int) $data['amount'],
            $allocations,
            $data['method'],
            $receivedAt,
            $request->user(),
            isset($data['note']) ? trim($data['note']) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Payment of '.self::money((int) $data['amount']).' recorded',
        ]);

        return to_route('receipts.show', ['receipt' => $receiptNumber]);
    }

    public function storeForSale(Request $request, Sale $sale, PaymentService $payments): RedirectResponse
    {
        if ($request->user()->role === UserRole::SALES && $sale->created_by !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(PaymentService::METHODS)],
            'received_at' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.required' => 'Enter an amount',
            'amount.min' => 'Enter an amount',
        ]);
        $receivedAt = Carbon::parse($data['received_at']);
        if ($request->user()->role === UserRole::SALES && $receivedAt->lt(now()->startOfMinute())) {
            return back()->withErrors(['received_at' => 'Only Owner and Admin can back-date payments.']);
        }

        $receiptNumber = $payments->recordForSale(
            $sale,
            (int) $data['amount'],
            $data['method'],
            $receivedAt,
            $request->user(),
            isset($data['note']) ? trim($data['note']) : null,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Payment of '.self::money((int) $data['amount']).' recorded',
        ]);

        return to_route('receipts.show', ['receipt' => $receiptNumber]);
    }

    public function receipt(Request $request, string $receipt): Response
    {
        $payments = Payment::query()
            ->with(['sale.location', 'sale.customer', 'receiver', 'reverser'])
            ->where('receipt_number', $receipt)
            ->orderBy('id')
            ->get();
        abort_if($payments->isEmpty(), 404);

        $total = (int) $payments->sum('amount');

        return Inertia::render('payments/receipt', [
            'receipt' => [
                'number' => $receipt,
                'received_at' => $payments->first()->received_at->toIso8601String(),
                'customer' => $payments->first()->customer?->only(['id', 'name', 'phone', 'city']),
                'location' => $payments->first()->sale->location->name,
                'received_by' => $payments->first()->receiver->name,
                'method' => $payments->first()->method,
                'note' => $payments->first()->note,
                'total' => $total,
                'balance_after' => $payments->first()->customer_balance_after,
                'reversed' => $payments->every(fn (Payment $payment): bool => $payment->status === 'reversed'),
                'reversal_reason' => $payments->first()->reversal_reason,
                'can_reverse_payment' => in_array(
                    $request->user()->role->value,
                    [UserRole::OWNER->value, UserRole::ADMIN->value],
                    true,
                ),
                'allocations' => $payments->map(fn (Payment $payment): array => [
                    'invoice_number' => $payment->sale->invoice_number,
                    'amount' => $payment->amount,
                ]),
            ],
        ]);
    }

    public function reverse(Request $request, string $receipt, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $payments->reverseReceipt($receipt, trim($data['reason']), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Payment receipt reversed.',
        ]);

        return back();
    }

    public function printReceipt(Request $request, string $receipt): RedirectResponse
    {
        $payment = Payment::query()->where('receipt_number', $receipt)->firstOrFail();
        PaymentAudit::query()->create([
            'user_id' => $request->user()->id,
            'customer_id' => $payment->customer_id,
            'payment_id' => $payment->id,
            'receipt_number' => $receipt,
            'event' => 'receipt_printed',
            'created_at' => now(),
        ]);

        return back();
    }

    private static function money(int $amount): string
    {
        return 'PKR '.number_format($amount);
    }
}
