<?php

use App\Models\Carpet;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\Sale;
use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

function setupSalesHistoryTestPiece(Location $location, int $price = 150000): Piece
{
    $carpet = Carpet::query()->firstOrCreate(
        ['sku' => 'TEST-SKU-01'],
        [
            'name' => 'Test Carpet',
            'design' => 'Geometric',
            'weave' => 'Hand-knotted',
            'size' => '5x8 ft',
            'color' => 'Red',
            'material' => 'Wool',
            'price' => $price,
        ]
    );

    return Piece::query()->create([
        'code' => 'CP-TEST-'.uniqid(),
        'carpet_id' => $carpet->id,
        'location_id' => $location->id,
        'status' => 'in_stock',
        'grade' => 'A',
        'landed_cost' => 100000,
        'price' => $price,
        'received_at' => now()->subDays(5),
    ]);
}

test('owner, admin, and sales roles can view sales history page, but others cannot', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($owner)->get(route('sales-history.index'))->assertOk();
    $this->actingAs($admin)->get(route('sales-history.index'))->assertOk();
    $this->actingAs($sales)->get(route('sales-history.index'))->assertOk();

    // Guest cannot view
    auth()->logout();
    $this->get(route('sales-history.index'))->assertRedirect(route('login'));
});

test('sales role only sees own invoices, while owner and admin see all invoices including voided', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $sales1 = User::factory()->create(['name' => 'Sales One', 'role' => UserRole::SALES]);
    $sales2 = User::factory()->create(['name' => 'Sales Two', 'role' => UserRole::SALES]);

    $display = Location::query()->firstOrCreate(
        ['name' => 'Hangu Display'],
        ['city' => 'Hangu', 'type' => 'display', 'active' => true]
    );

    $customer = Customer::query()->create(['name' => 'Customer A', 'phone' => '0300-1111111']);

    $sale1 = Sale::query()->create([
        'invoice_number' => 'INV-2026-9001',
        'customer_id' => $customer->id,
        'location_id' => $display->id,
        'created_by' => $sales1->id,
        'status' => 'completed',
        'subtotal' => 150000,
        'discount' => 0,
        'total' => 150000,
        'sold_at' => now()->subDays(2),
    ]);

    $sale2 = Sale::query()->create([
        'invoice_number' => 'INV-2026-9002',
        'customer_id' => null,
        'location_id' => $display->id,
        'created_by' => $sales2->id,
        'status' => 'completed',
        'subtotal' => 200000,
        'discount' => 0,
        'total' => 200000,
        'sold_at' => now()->subDay(),
    ]);

    $sale3Void = Sale::query()->create([
        'invoice_number' => 'INV-2026-9003',
        'customer_id' => null,
        'location_id' => $display->id,
        'created_by' => $sales1->id,
        'status' => 'void',
        'subtotal' => 100000,
        'discount' => 0,
        'total' => 100000,
        'sold_at' => now(),
    ]);

    // Sales 1 should only see sale1 and sale3Void (created by sales1)
    $this->actingAs($sales1)
        ->get(route('sales-history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/index')
            ->where('is_sales_role', true)
            ->where('can_void', false)
            ->where('can_create_payment', true)
            ->has('sales', 2)
            ->where('sales.0.invoice_number', 'INV-2026-9003')
            ->where('sales.1.invoice_number', 'INV-2026-9001')
        );

    // Owner should see all 3 invoices
    $this->actingAs($owner)
        ->get(route('sales-history.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/index')
            ->where('is_sales_role', false)
            ->where('can_void', true)
            ->where('can_create_payment', true)
            ->has('sales', 3)
        );
});

test('user can record a payment for an open invoice', function () {
    $sales = User::factory()->create(['role' => UserRole::SALES]);
    $display = Location::query()->firstOrCreate(
        ['name' => 'Kohat Display'],
        ['city' => 'Kohat', 'type' => 'display', 'active' => true]
    );
    $customer = Customer::query()->create(['name' => 'Paying Customer', 'phone' => '0300-2222222']);

    $sale = Sale::query()->create([
        'invoice_number' => 'INV-2026-9004',
        'customer_id' => $customer->id,
        'location_id' => $display->id,
        'created_by' => $sales->id,
        'status' => 'completed',
        'subtotal' => 100000,
        'discount' => 0,
        'total' => 100000,
        'sold_at' => now(),
    ]);

    // Attempt paying more than balance due -> should fail
    $this->actingAs($sales)
        ->postJson(route('sales.payments.store', $sale), [
            'amount' => 150000,
            'method' => 'cash',
        ])
        ->assertStatus(422);

    // Record valid partial payment
    $this->actingAs($sales)
        ->postJson(route('sales.payments.store', $sale), [
            'amount' => 40000,
            'method' => 'bank_transfer',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Payment of PKR 40,000 recorded',
            'sale' => [
                'paid' => 40000,
                'balance' => 60000,
                'status' => 'Partial',
            ],
        ]);

    expect($sale->fresh()->paidAmount())->toBe(40000)
        ->and($sale->fresh()->balanceDue())->toBe(60000)
        ->and($sale->fresh()->paymentStatus())->toBe('Partial');
});

test('owner can void a completed sale and pieces return to in_stock at display with history entry', function () {
    $owner = User::factory()->create(['name' => 'Boss Owner', 'role' => UserRole::OWNER]);
    $display = Location::query()->firstOrCreate(
        ['name' => 'Hangu Display'],
        ['city' => 'Hangu', 'type' => 'display', 'active' => true]
    );

    $piece = setupSalesHistoryTestPiece($display, 180000);
    $piece->update(['status' => 'sold']);

    $sale = Sale::query()->create([
        'invoice_number' => 'INV-2026-9005',
        'customer_id' => null,
        'location_id' => $display->id,
        'created_by' => $owner->id,
        'status' => 'completed',
        'subtotal' => 180000,
        'discount' => 0,
        'total' => 180000,
        'sold_at' => now(),
    ]);

    $sale->items()->create([
        'piece_id' => $piece->id,
        'piece_code' => $piece->code,
        'carpet_id' => $piece->carpet_id,
        'sku' => $piece->carpet->sku,
        'name' => $piece->displayName(),
        'unit_price' => 180000,
        'unit_cost' => 100000,
    ]);

    PieceMovement::query()->create([
        'piece_id' => $piece->id,
        'type' => 'sale',
        'from_location_id' => $display->id,
        'to_location_id' => null,
        'reference' => $sale->invoice_number,
        'user_id' => $owner->id,
        'moved_at' => now(),
    ]);

    // Void the sale
    $this->actingAs($owner)
        ->postJson(route('sales.void', $sale))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => "{$sale->invoice_number} voided, pieces back in stock",
        ]);

    expect($sale->fresh()->status)->toBe('void')
        ->and($piece->fresh()->status)->toBe('in_stock')
        ->and($piece->fresh()->location_id)->toBe($display->id);

    $voidMovement = PieceMovement::query()
        ->where('piece_id', $piece->id)
        ->where('type', 'sale_void')
        ->first();

    expect($voidMovement)->not->toBeNull()
        ->and($voidMovement->reference)->toBe($sale->invoice_number)
        ->and($voidMovement->user_id)->toBe($owner->id);
});
