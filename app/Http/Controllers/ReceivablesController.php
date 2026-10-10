<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Location;
use App\Models\Sale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReceivablesController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Sale::query()
            ->with(['customer', 'location', 'payments'])
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->whereHas('customer')
            ->whereHas('location', fn ($location) => $location->where('type', 'display'))
            ->when($request->filled('location'), fn ($builder) => $builder->where('location_id', $request->integer('location')))
            ->when($request->filled('city'), fn ($builder) => $builder->whereHas('customer', fn ($customer) => $customer->where('city', $request->string('city')->toString())))
            ->when($request->filled('search'), fn ($builder) => $builder->whereHas('customer', fn ($customer) => $customer->where('name', 'like', '%'.$request->string('search')->toString().'%')));

        $sales = $query->orderBy('sold_at')->get();
        $open = $sales->map(fn (Sale $sale): array => [
            'sale' => $sale,
            'balance' => $sale->balanceDue(),
            'age' => (int) $sale->sold_at->copy()->startOfDay()->diffInDays(now()->startOfDay()),
        ])->filter(fn (array $row): bool => $row['balance'] > 0);
        $customers = $open->groupBy(fn (array $row): int => $row['sale']->customer_id)
            ->map(function ($rows): array {
                $customer = $rows->first()['sale']->customer;
                $data = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'city' => $customer->city,
                    'open_invoices' => $rows->count(),
                    'balance_due' => (int) $rows->sum('balance'),
                    'age_0_30' => 0,
                    'age_31_60' => 0,
                    'age_61_90' => 0,
                    'age_90_plus' => 0,
                    'invoices' => [],
                ];
                foreach ($rows as $row) {
                    $bucket = match (true) {
                        $row['age'] <= 30 => 'age_0_30',
                        $row['age'] <= 60 => 'age_31_60',
                        $row['age'] <= 90 => 'age_61_90',
                        default => 'age_90_plus',
                    };
                    $data[$bucket] += $row['balance'];
                    $data['invoices'][] = [
                        'id' => $row['sale']->id,
                        'invoice_number' => $row['sale']->invoice_number,
                        'sold_at' => $row['sale']->sold_at->toIso8601String(),
                        'status' => $row['sale']->paymentStatus(),
                        'balance' => $row['balance'],
                    ];
                }

                return $data;
            })
            ->sortByDesc('balance_due')
            ->values();
        $total = (int) $customers->sum('balance_due');

        return Inertia::render('receivables/index', [
            'customers' => $customers,
            'can_receive_payment' => in_array(
                'sales.create',
                config('roles.'.$request->user()->role->value, []),
                true,
            ),
            'can_receive_payment' => in_array(
                'sales.create',
                config('roles.'.$request->user()->role->value, []),
                true,
            ),
            'metrics' => [
                'total_outstanding' => $total,
                'customers_with_balance' => $customers->count(),
                'oldest_invoice_age' => (int) $open->max('age'),
                'amount_over_60_days' => (int) $customers->sum('age_61_90') + (int) $customers->sum('age_90_plus'),
            ],
            'filters' => $request->only(['location', 'city', 'search']),
            'locations' => Location::query()->where('type', 'display')->orderBy('name')->get(['id', 'name']),
            'cities' => Customer::query()
                ->whereHas('sales', fn ($sales) => $sales->where('status', 'completed'))
                ->orderBy('city')
                ->distinct()
                ->pluck('city')
                ->values(),
        ]);
    }
}
