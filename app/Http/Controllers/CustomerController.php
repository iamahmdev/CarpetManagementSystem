<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PaymentAudit;
use App\Models\Sale;
use App\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('customers/index', [
            'customers' => Customer::query()
                ->withCount(['sales as invoices_count' => fn ($query) => $query->where('status', 'completed')])
                ->withSum(['sales as purchased_total' => fn ($query) => $query->where('status', 'completed')], 'total')
                ->withSum(['payments as paid_total' => fn ($query) => $query->where('status', 'active')], 'amount')
                ->with('sales.payments')
                ->orderBy('name')
                ->get()
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'city' => $customer->city,
                    'invoices_count' => $customer->invoices_count,
                    'purchased_total' => (int) ($customer->purchased_total ?? 0),
                    'balance_due' => (int) $customer->sales
                        ->where('status', 'completed')
                        ->sum(fn (Sale $sale): int => $sale->balanceDue()),
                ]),
            'can_create' => in_array(
                'sales.create',
                config('roles.'.$request->user()->role->value, []),
                true,
            ),
        ]);
    }

    public function show(Request $request, Customer $customer): Response
    {
        $sales = $customer->sales()
            ->with(['payments.receiver', 'payments.reverser', 'location', 'items'])
            ->orderBy('sold_at')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($sales as $sale) {
            $rows[] = [
                'at' => $sale->sold_at,
                'type' => 'Invoice',
                'reference' => $sale->invoice_number,
                'method' => null,
                'debit' => $sale->total,
                'credit' => 0,
                'sale_id' => $sale->id,
                'receipt_number' => null,
                'reversal_reason' => null,
            ];
            if ($sale->status === 'void') {
                $rows[] = [
                    'at' => $sale->updated_at,
                    'type' => 'Void',
                    'reference' => $sale->invoice_number,
                    'method' => null,
                    'debit' => 0,
                    'credit' => $sale->total,
                    'sale_id' => $sale->id,
                    'receipt_number' => null,
                    'reversal_reason' => null,
                ];
            }
            foreach ($sale->payments as $payment) {
                $rows[] = [
                    'at' => $payment->received_at,
                    'type' => 'Payment',
                    'reference' => $payment->receipt_number,
                    'method' => $payment->method,
                    'debit' => 0,
                    'credit' => $payment->amount,
                    'sale_id' => $sale->id,
                    'receipt_number' => $payment->receipt_number,
                    'reversed' => $payment->status === 'reversed',
                    'reversal_reason' => null,
                ];
                if ($payment->status === 'reversed') {
                    $rows[] = [
                        'at' => $payment->reversed_at,
                        'type' => 'Payment reversed',
                        'reference' => $payment->receipt_number,
                        'method' => $payment->method,
                        'debit' => $payment->amount,
                        'credit' => 0,
                        'sale_id' => $sale->id,
                        'receipt_number' => $payment->receipt_number,
                        'reversal_reason' => $payment->reversal_reason,
                    ];
                }
            }
        }

        usort($rows, fn (array $a, array $b): int => ($a['at']->timestamp <=> $b['at']->timestamp)
            ?: (['Invoice' => 0, 'Payment' => 1, 'Payment reversed' => 2, 'Void' => 3][$a['type']]
                <=> ['Invoice' => 0, 'Payment' => 1, 'Payment reversed' => 2, 'Void' => 3][$b['type']]));
        $runningBalance = 0;
        $statement = array_map(function (array $row) use (&$runningBalance): array {
            $runningBalance += $row['debit'] - $row['credit'];

            return [
                ...$row,
                'at' => $row['at']->toIso8601String(),
                'running_balance' => $runningBalance,
            ];
        }, $rows);

        $completedSales = $sales->where('status', 'completed');
        $unpaid = $completedSales
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'sold_at' => $sale->sold_at->toIso8601String(),
                'status' => $sale->paymentStatus(),
                'balance' => $sale->balanceDue(),
            ])
            ->filter(fn (array $sale): bool => $sale['balance'] > 0)
            ->values();

        $totalDue = (int) $unpaid->sum('balance');
        $oldest = $unpaid->sortBy('sold_at')->first();

        return Inertia::render('customers/ledger', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'city' => $customer->city,
            ],
            'metrics' => [
                'total_invoiced' => (int) $completedSales->sum('total'),
                'total_paid' => (int) $completedSales->sum(fn (Sale $sale): int => $sale->paidAmount()),
                'balance_due' => $totalDue,
                'oldest_age_days' => $oldest
                    ? (int) Carbon::parse($oldest['sold_at'])->startOfDay()->diffInDays(now()->startOfDay())
                    : 0,
            ],
            'statement' => $statement,
            'unpaid_invoices' => $unpaid,
            'can_receive_payment' => in_array(
                'sales.create',
                config('roles.'.$request->user()->role->value, []),
                true,
            ),
            'can_reverse_payment' => in_array(
                $request->user()->role,
                [UserRole::OWNER, UserRole::ADMIN],
                true,
            ),
            'allow_backdate' => $request->user()->role !== UserRole::SALES,
            'payment_methods' => [
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'bank_transfer', 'label' => 'Bank transfer'],
                ['value' => 'cheque', 'label' => 'Cheque'],
                ['value' => 'mobile_wallet', 'label' => 'Mobile wallet'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'return_to_sale' => ['sometimes', 'boolean'],
        ], [
            'name.required' => 'Enter a name',
        ]);

        $customer = Customer::query()->create([
            'name' => trim($data['name']),
            'phone' => trim($data['phone'] ?? '') ?: '-',
            'city' => trim($data['city'] ?? '') ?: '-',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'message' => "{$customer->name} added",
            ]);
        }

        if ($request->boolean('return_to_sale')) {
            return to_route('new-sale.index', ['customer_id' => $customer->id]);
        }

        return to_route('customers.show', $customer);
    }

    public function printStatement(Request $request, Customer $customer): RedirectResponse
    {
        PaymentAudit::query()->create([
            'user_id' => $request->user()->id,
            'customer_id' => $customer->id,
            'event' => 'statement_printed',
            'created_at' => now(),
        ]);

        return back();
    }
}
