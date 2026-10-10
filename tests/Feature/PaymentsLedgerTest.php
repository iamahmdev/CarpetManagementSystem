<?php

use App\Models\Carpet;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PieceService;
use App\UserRole;
use Carbon\Carbon;
use Database\Seeders\CarpetSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

function ledgerLocation(): Location
{
    return Location::query()->create([
        'name' => 'Hangu Display',
        'type' => 'display',
        'city' => 'Hangu',
        'active' => true,
    ]);
}

function ledgerSale(User $user, Customer $customer, Location $location, int $total, string $date): Sale
{
    static $invoice = 1000;

    return Sale::query()->create([
        'invoice_number' => 'INV-2026-'.str_pad((string) ++$invoice, 4, '0', STR_PAD_LEFT),
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'created_by' => $user->id,
        'status' => 'completed',
        'subtotal' => $total,
        'discount' => 0,
        'total' => $total,
        'sold_at' => $date,
    ]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

test('sales permissions and role grants match the ledger requirements', function () {
    expect(config('permissions'))->toContain('reports.profit', 'reports.receivables')
        ->and(config('roles.owner'))->toContain('sales.create', 'sales.view', 'sales.void', 'reports.receivables')
        ->and(config('roles.owner'))->toContain('reports.profit')
        ->and(config('roles.admin'))->toContain('sales.create', 'sales.view', 'sales.void', 'reports.receivables')
        ->and(config('roles.admin'))->not->toContain('reports.profit')
        ->and(config('roles.sales'))->toContain('sales.create', 'sales.view')
        ->and(config('roles.sales'))->not->toContain('sales.void', 'reports.receivables', 'reports.profit');
});

test('only owners can open the profit page', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::OWNER]))
        ->get(route('profit-report.index'))
        ->assertOk();

    $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
        ->get(route('profit-report.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => UserRole::SALES]))
        ->get(route('profit-report.index'))
        ->assertForbidden();
});

test('users without sales permissions are forbidden on sales and customer workflows', function () {
    $user = User::factory()->create(['role' => UserRole::SALES]);
    config(['roles.sales' => []]);

    $this->actingAs($user)
        ->get(route('customers.index'))
        ->assertForbidden();
    $this->get(route('sales-history.index'))->assertForbidden();
    $this->post(route('customers.store'), ['name' => 'Blocked'])->assertForbidden();
    $this->post(route('sales.store'), [])->assertForbidden();
});

test('guests are redirected and users without report permission are forbidden', function () {
    $this->get(route('customers.index'))->assertRedirect(route('login'));
    $this->get(route('receivables.index'))->assertRedirect(route('login'));

    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($sales)->get(route('receivables.index'))->assertForbidden();
});

test('owner can create a partial sale, receive its balance, and view a zero-ending ledger', function () {
    app(CarpetSeeder::class)->run();
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Carpet Buyer', 'phone' => '03001234567', 'city' => 'Hangu']);
    $location = ledgerLocation();
    $carpet = Carpet::query()->where('sku', 'TBZ-6x9-IND')->firstOrFail();
    $piece = app(PieceService::class)->receive($carpet, $location, $user, 1, 100000, 100000, 0, 0, 0, 0)->firstOrFail();

    $this->actingAs($user)->post(route('sales.store'), [
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'discount' => 0,
        'paid_now' => 111000,
        'method' => 'cash',
        'items' => [['piece_id' => $piece->id, 'unit_price' => 185000]],
    ])->assertRedirect();

    $sale = Sale::query()->with('payments')->firstOrFail();
    expect($sale->paymentStatus())->toBe('Partial')
        ->and($sale->balanceDue())->toBe(74000)
        ->and($sale->invoice_number)->toMatch('/^INV-2026-\d{4}$/');

    $this->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/index')
            ->where('customers.0.balance_due', 74000));

    $this->post(route('sales.payments.store', $sale), [
        'amount' => 74000,
        'method' => 'bank_transfer',
        'received_at' => '2026-10-08T12:00',
    ])->assertRedirect(route('receipts.show', 'RCP-2026-0002'));

    expect($sale->fresh()->paymentStatus())->toBe('Paid')
        ->and($sale->fresh()->balanceDue())->toBe(0)
        ->and(Payment::query()->where('receipt_number', 'RCP-2026-0002')->sum('amount'))->toBe(74000);

    $this->get(route('customers.show', $customer))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('customers/ledger')
            ->where('metrics.balance_due', 0)
            ->has('statement', 3)
            ->where('statement.2.running_balance', 0));
});

test('a walk-in sale with a balance is rejected and excess invoice payment is not saved', function () {
    app(CarpetSeeder::class)->run();
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $location = ledgerLocation();
    $carpet = Carpet::query()->where('sku', 'TBZ-6x9-IND')->firstOrFail();
    $piece = app(PieceService::class)->receive($carpet, $location, $user, 1, 100000, 100000, 0, 0, 0, 0)->firstOrFail();

    $this->actingAs($user)->post(route('sales.store'), [
        'location_id' => $location->id,
        'paid_now' => 111000,
        'method' => 'cash',
        'items' => [['piece_id' => $piece->id, 'unit_price' => 185000]],
    ])->assertSessionHasErrors('customer_id');
    expect(Sale::query()->count())->toBe(0);

    $customer = Customer::query()->create(['name' => 'Buyer']);
    $sale = ledgerSale($user, $customer, $location, 74000, now()->toDateTimeString());

    $this->post(route('sales.payments.store', $sale), [
        'amount' => 80000,
        'method' => 'cash',
        'received_at' => '2026-10-08T12:00',
    ])->assertSessionHasErrors('amount');

    expect(Payment::query()->count())->toBe(0);
});

test('empty and zero payment amounts show the enter an amount message', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Amount Validation Buyer']);
    $sale = ledgerSale($user, $customer, ledgerLocation(), 74000, now()->toDateTimeString());

    foreach (['', '0'] as $amount) {
        $response = $this->actingAs($user)->post(route('sales.payments.store', $sale), [
            'amount' => $amount,
            'method' => 'cash',
            'received_at' => '2026-10-08T12:00',
        ]);
        $response->assertSessionHasErrors('amount');
        expect(session('errors')->first('amount'))->toBe('Enter an amount');
    }

    $response = $this->post(route('customers.payments.store', $customer), [
        'amount' => '0',
        'method' => 'cash',
        'received_at' => '2026-10-08T12:00',
    ]);
    $response->assertSessionHasErrors('amount');
    expect(session('errors')->first('amount'))->toBe('Enter an amount')
        ->and(Payment::query()->count())->toBe(0);
});

test('on-account payments allocate oldest invoices first and receipt reversal preserves history', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Account Customer']);
    $location = ledgerLocation();
    $oldest = ledgerSale($owner, $customer, $location, 50000, now()->subDays(60)->toDateTimeString());
    $newer = ledgerSale($owner, $customer, $location, 70000, now()->subDays(10)->toDateTimeString());

    $this->actingAs($owner)->post(route('customers.payments.store', $customer), [
        'amount' => 80000,
        'method' => 'cheque',
        'received_at' => '2026-10-08T12:00',
    ])->assertRedirect(route('receipts.show', 'RCP-2026-0001'));

    $payments = Payment::query()->where('receipt_number', 'RCP-2026-0001')->orderBy('sale_id')->get();
    expect($payments)->toHaveCount(2)
        ->and($payments->firstWhere('sale_id', $oldest->id)->amount)->toBe(50000)
        ->and($payments->firstWhere('sale_id', $newer->id)->amount)->toBe(30000)
        ->and($oldest->fresh()->balanceDue())->toBe(0)
        ->and($newer->fresh()->balanceDue())->toBe(40000);

    $this->post(route('receipts.reverse', 'RCP-2026-0001'), [
        'reason' => 'Wrong cheque entered',
    ])->assertRedirect();

    expect(Payment::query()->where('receipt_number', 'RCP-2026-0001')->where('status', 'reversed')->count())->toBe(2)
        ->and($oldest->fresh()->balanceDue())->toBe(50000)
        ->and($newer->fresh()->balanceDue())->toBe(70000)
        ->and($oldest->fresh()->paymentStatus())->toBe('Unpaid');
});

test('sales can receive payments and view ledgers but cannot reverse receipts or view receivables', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Sales User Buyer']);
    $location = ledgerLocation();
    $sale = ledgerSale($owner, $customer, $location, 9000, now()->toDateTimeString());
    $receipt = app(PaymentService::class)->recordForSale($sale, 1000, 'cash', now(), $owner);

    $this->actingAs($salesUser)
        ->get(route('customers.show', $customer))
        ->assertOk();
    $this->get(route('receivables.index'))->assertForbidden();
    $this->post(route('receipts.reverse', $receipt), ['reason' => 'No permission'])->assertForbidden();
    $this->post(route('customers.payments.store', $customer), [
        'amount' => 1000,
        'method' => 'cash',
        'received_at' => '2026-10-08T12:00',
    ])->assertRedirect();
});

test('admin can view receivables and ageing places a 45 day invoice in the 31 to 60 bucket', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $customer = Customer::query()->create(['name' => 'Ageing Customer']);
    $sale = ledgerSale($admin, $customer, ledgerLocation(), 25000, now()->subDays(45)->toDateTimeString());

    $this->actingAs($admin)
        ->get(route('receivables.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('receivables/index')
            ->where('customers.0.age_0_30', 0)
            ->where('customers.0.age_31_60', 25000)
            ->where('customers.0.age_61_90', 0)
            ->where('customers.0.age_90_plus', 0)
            ->where('metrics.amount_over_60_days', 0));
});

test('admin can reverse a payment receipt', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $customer = Customer::query()->create(['name' => 'Admin Reversal Buyer']);
    $sale = ledgerSale($admin, $customer, ledgerLocation(), 25000, now()->toDateTimeString());
    $receipt = app(PaymentService::class)->recordForSale($sale, 5000, 'cash', now(), $admin);

    $this->actingAs($admin)
        ->post(route('receipts.reverse', $receipt), ['reason' => 'Incorrect entry'])
        ->assertRedirect();

    expect(Payment::query()->where('receipt_number', $receipt)->value('status'))->toBe('reversed')
        ->and($sale->fresh()->balanceDue())->toBe(25000);
});

test('voided invoices are excluded from receivables and on-account allocations', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Voided Allocation Buyer']);
    $location = ledgerLocation();
    $voidedSale = ledgerSale($owner, $customer, $location, 50000, now()->subDays(10)->toDateTimeString());
    $voidedSale->update(['status' => 'void']);
    $openSale = ledgerSale($owner, $customer, $location, 30000, now()->subDays(5)->toDateTimeString());

    $this->actingAs($owner)
        ->get(route('receivables.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customers.0.invoices.0.id', $openSale->id)
            ->where('customers.0.invoices', fn ($invoices) => $invoices->pluck('id')->all() === [$openSale->id]));

    $receipt = app(PaymentService::class)->recordForCustomer(
        $customer,
        10000,
        [],
        'cash',
        now(),
        $owner,
    );

    expect(Payment::query()->where('receipt_number', $receipt)->pluck('sale_id')->all())
        ->toBe([$openSale->id])
        ->and(Payment::query()->where('sale_id', $voidedSale->id)->count())->toBe(0);
});

test('a completed payment never changes sales revenue or totals and voiding with active payment is blocked', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Stable Profit']);
    $sale = ledgerSale($owner, $customer, ledgerLocation(), 50000, now()->toDateTimeString());
    $beforeRevenue = (int) Sale::query()->sum('total');
    $receipt = app(PaymentService::class)->recordForSale($sale, 10000, 'cash', now(), $owner);
    app(PaymentService::class)->reverseReceipt($receipt, 'Test', $owner);

    expect((int) Sale::query()->sum('total'))->toBe($beforeRevenue)
        ->and((int) Sale::query()->sum('total'))->toBe(50000);

    app(PaymentService::class)->recordForSale($sale, 1000, 'cash', now(), $owner);
    $this->actingAs($owner)
        ->post(route('sales.void', $sale))
        ->assertSessionHasErrors('sale');
    expect($sale->fresh()->status)->toBe('completed');
});

test('legacy migration preserves every sale paid amount and status including identical payments', function () {
    Schema::table('sales', function (Blueprint $table) {
        $table->json('payments')->nullable();
    });
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $customer = Customer::query()->create(['name' => 'Legacy Buyer']);
    $location = ledgerLocation();
    $partialSale = ledgerSale($user, $customer, $location, 50000, now()->toDateTimeString());
    $paidSale = ledgerSale($user, $customer, $location, 34000, now()->toDateTimeString());
    $unpaidSale = ledgerSale($user, $customer, $location, 20000, now()->toDateTimeString());
    $legacyPayments = [
        $partialSale->id => [
            ['amount' => 17000, 'method' => 'cash', 'at' => '2026-10-01 09:00:00'],
            ['amount' => 17000, 'method' => 'cash', 'at' => '2026-10-01 09:00:00'],
        ],
        $paidSale->id => [
            ['amount' => 34000, 'method' => 'bank_transfer', 'at' => '2026-10-02 09:00:00'],
        ],
        $unpaidSale->id => [],
    ];
    foreach ($legacyPayments as $saleId => $payments) {
        DB::table('sales')->where('id', $saleId)->update([
            'payments' => json_encode($payments, JSON_THROW_ON_ERROR),
        ]);
    }

    $before = [];
    foreach ($legacyPayments as $saleId => $payments) {
        $sale = Sale::query()->findOrFail($saleId);
        $paidAmount = array_sum(array_column($payments, 'amount'));
        $before[$saleId] = [
            'paid' => $paidAmount,
            'status' => $paidAmount >= $sale->total ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Unpaid'),
        ];
    }

    $migration = require database_path('migrations/2026_10_08_131000_migrate_legacy_sale_payments.php');
    $migration->up();
    $migration->up();

    foreach ($before as $saleId => $expected) {
        $sale = Sale::query()->findOrFail($saleId);
        expect($sale->paidAmount())->toBe($expected['paid'])
            ->and($sale->paymentStatus())->toBe($expected['status']);
    }
    expect(Payment::query()->where('sale_id', $partialSale->id)->count())->toBe(2);
});
