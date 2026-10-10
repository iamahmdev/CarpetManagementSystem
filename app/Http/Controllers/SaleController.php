<?php

namespace App\Http\Controllers;

use App\Models\Carpet;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Sale;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SaleController extends Controller
{
    public function index(Request $request): Response
    {
        $sales = Sale::query()
            ->with(['customer', 'location', 'payments'])
            ->when(
                $request->user()->role->value === 'sales',
                fn ($query) => $query->where('created_by', $request->user()->id),
            )
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer' => $sale->customer?->name ?? 'Walk-in',
                'location' => $sale->location->name,
                'sold_at' => $sale->sold_at->toIso8601String(),
                'total' => $sale->total,
                'paid' => $sale->paidAmount(),
                'balance' => $sale->balanceDue(),
                'status' => $sale->paymentStatus(),
            ]);

        return Inertia::render('sales/index', ['sales' => $sales]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('sales/create', [
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'city']),
            'locations' => Location::query()
                ->where('type', 'display')
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'carpets' => Carpet::query()->orderBy('created_at')->orderBy('id')->get(['id', 'sku', 'name', 'price']),
            'payment_methods' => [
                ['value' => 'cash', 'label' => 'Cash'],
                ['value' => 'bank_transfer', 'label' => 'Bank transfer'],
                ['value' => 'cheque', 'label' => 'Cheque'],
                ['value' => 'mobile_wallet', 'label' => 'Mobile wallet'],
            ],
            'selected_customer_id' => $request->integer('customer_id') ?: null,
        ]);
    }

    public function store(Request $request, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('type', 'display')->where('active', true)),
            ],
            'discount' => ['nullable', 'integer'],
            'paid_now' => ['nullable', 'integer'],
            'method' => ['required', Rule::in(PaymentService::METHODS)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.carpet_id' => ['required', 'integer', 'exists:carpets,id'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
        ]);

        return DB::transaction(function () use ($data, $request, $payments): RedirectResponse {
            $catalog = Carpet::query()->whereIn('id', collect($data['items'])->pluck('carpet_id'))->get()->keyBy('id');
            $subtotal = (int) collect($data['items'])->sum('unit_price');
            $discount = min(max(0, (int) ($data['discount'] ?? 0)), $subtotal);
            $total = max(0, $subtotal - $discount);
            $paid = min(max(0, (int) ($data['paid_now'] ?? $total)), $total);

            if ($paid < $total && empty($data['customer_id'])) {
                return back()->withErrors(['customer_id' => 'Choose a customer to record a balance due'])->withInput();
            }

            $year = now()->year;
            DB::table('document_sequences')->insertOrIgnore([
                'document_type' => 'invoice',
                'year' => $year,
                'last_number' => 0,
            ]);
            $sequence = DB::table('document_sequences')
                ->where('document_type', 'invoice')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            $invoiceNumber = sprintf('INV-%d-%04d', $year, $sequence->last_number + 1);
            DB::table('document_sequences')
                ->where('document_type', 'invoice')
                ->where('year', $year)
                ->update(['last_number' => $sequence->last_number + 1]);

            $sale = Sale::query()->create([
                'invoice_number' => $invoiceNumber,
                'customer_id' => $data['customer_id'] ?? null,
                'location_id' => $data['location_id'],
                'created_by' => $request->user()->id,
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'sold_at' => now(),
            ]);
            foreach ($data['items'] as $item) {
                $carpet = $catalog->get($item['carpet_id']);
                $sale->items()->create([
                    'carpet_id' => $carpet->id,
                    'sku' => $carpet->sku,
                    'name' => $carpet->name,
                    'unit_price' => (int) $item['unit_price'],
                ]);
            }

            if ($paid > 0) {
                $payments->recordForSale($sale, $paid, $data['method'], now(), $request->user());
            }

            return to_route('sales.show', $sale);
        });
    }

    public function show(Request $request, Sale $sale): Response
    {
        if ($request->user()->role->value === 'sales' && $sale->created_by !== $request->user()->id) {
            abort(403);
        }

        $sale->load(['customer', 'location', 'items', 'payments.receiver', 'payments.reverser']);

        return Inertia::render('sales/show', [
            'sale' => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'customer' => $sale->customer?->only(['id', 'name', 'phone', 'city']),
                'location' => $sale->location->name,
                'sold_at' => $sale->sold_at->toIso8601String(),
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'total' => $sale->total,
                'paid' => $sale->paidAmount(),
                'balance' => $sale->balanceDue(),
                'status' => $sale->paymentStatus(),
                'items' => $sale->items->map(fn ($item): array => [
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'unit_price' => $item->unit_price,
                ]),
                'payments' => $sale->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'received_at' => $payment->received_at->toIso8601String(),
                    'status' => $payment->status,
                    'reversal_reason' => $payment->reversal_reason,
                ]),
                'can_receive_payment' => in_array('sales.create', config('roles.'.$request->user()->role->value, []), true),
                'can_reverse_payment' => in_array($request->user()->role->value, ['owner', 'admin'], true),
            ],
        ]);
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        if ($sale->activePayments()->exists()) {
            return back()->withErrors([
                'sale' => 'This invoice has active payments. Reverse its payment receipt before voiding it.',
            ]);
        }

        $sale->update(['status' => 'void']);

        return to_route('sales-history.index');
    }
}
