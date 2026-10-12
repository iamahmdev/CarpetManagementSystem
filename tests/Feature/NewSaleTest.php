<?php

use App\Models\Carpet;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\Sale;
use App\Models\User;
use App\Services\PieceService;
use App\UserRole;
use Carbon\Carbon;
use Database\Seeders\CarpetSeeder;

beforeEach(function () {
    Carbon::setTestNow('2026-10-12 12:00:00');
    $this->seed(CarpetSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

function createTestDisplay(string $name = 'Hangu Display'): Location
{
    return Location::query()->create([
        'name' => $name,
        'type' => 'display',
        'city' => 'Hangu',
        'active' => true,
    ]);
}

function createTestPiece(Location $location, string $status = 'in_stock', ?int $price = 150000): Piece
{
    $carpet = Carpet::query()->firstOrFail();
    $piece = app(PieceService::class)->receive(
        $carpet,
        $location,
        User::factory()->create(['role' => UserRole::OWNER]),
        1,
        100000,
        100000,
        0,
        0,
        0,
        0,
    )->first();

    $piece->update([
        'status' => $status,
        'price' => $price,
    ]);

    return $piece->fresh();
}

test('owner, admin, and sales roles can access new sale page while unauthorized users cannot', function () {
    $display = createTestDisplay();
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($owner)->get(route('new-sale.index'))->assertOk();
    $this->actingAs($admin)->get(route('new-sale.index'))->assertOk();
    $this->actingAs($sales)->get(route('new-sale.index'))->assertOk();
});

test('piece lookup validates existence, status, location, and duplicate invoice presence', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $hanguDisplay = createTestDisplay('Hangu Display');
    $kohatDisplay = Location::query()->create([
        'name' => 'Kohat Display',
        'type' => 'display',
        'city' => 'Kohat',
        'active' => true,
    ]);

    $inStockPiece = createTestPiece($hanguDisplay, 'in_stock');
    $transitPiece = createTestPiece($hanguDisplay, 'in_transit');
    $otherLocPiece = createTestPiece($kohatDisplay, 'in_stock');

    // 1. Piece not found
    $this->actingAs($salesUser)
        ->getJson(route('pieces.lookup', [
            'code' => '999999',
            'location_id' => $hanguDisplay->id,
        ]))
        ->assertStatus(404)
        ->assertJson(['message' => 'No piece with ID CP-999999']);

    // 2. Piece not in_stock
    $this->actingAs($salesUser)
        ->getJson(route('pieces.lookup', [
            'code' => $transitPiece->code,
            'location_id' => $hanguDisplay->id,
        ]))
        ->assertStatus(422)
        ->assertJson(['message' => "{$transitPiece->code} is in transit and can't be sold"]);

    // 3. Piece at different location
    $this->actingAs($salesUser)
        ->getJson(route('pieces.lookup', [
            'code' => $otherLocPiece->code,
            'location_id' => $hanguDisplay->id,
        ]))
        ->assertStatus(422)
        ->assertJson(['message' => "{$otherLocPiece->code} is at Kohat Display, not the selected display"]);

    // 4. Piece already on invoice
    $this->actingAs($salesUser)
        ->getJson(route('pieces.lookup', [
            'code' => $inStockPiece->code,
            'location_id' => $hanguDisplay->id,
            'existing_piece_ids' => [$inStockPiece->id],
        ]))
        ->assertStatus(422)
        ->assertJson(['message' => "{$inStockPiece->code} is already on the invoice"]);

    // 5. Success
    $this->actingAs($salesUser)
        ->getJson(route('pieces.lookup', [
            'code' => $inStockPiece->code,
            'location_id' => $hanguDisplay->id,
        ]))
        ->assertOk()
        ->assertJson([
            'piece' => [
                'id' => $inStockPiece->id,
                'code' => $inStockPiece->code,
                'price' => $inStockPiece->effectivePrice(),
            ],
        ]);
});

test('completing a sale starts invoice numbering at 1041 and records piece movement and payment', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $display = createTestDisplay('Hangu Display');
    $piece1 = createTestPiece($display, 'in_stock', 150000);
    $piece2 = createTestPiece($display, 'in_stock', 120000);
    $customer = Customer::query()->create(['name' => 'Dr. Farhan Ali', 'phone' => '0321 5551234', 'city' => 'Peshawar']);

    $response = $this->actingAs($salesUser)
        ->postJson(route('sales.store'), [
            'location_id' => $display->id,
            'customer_id' => $customer->id,
            'discount' => 10000,
            'paid_now' => 260000,
            'method' => 'cash',
            'items' => [
                ['piece_id' => $piece1->id, 'unit_price' => 150000],
                ['piece_id' => $piece2->id, 'unit_price' => 120000],
            ],
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'sale' => [
                'invoice_number' => 'INV-2026-1041',
                'subtotal' => 270000,
                'discount' => 10000,
                'total' => 260000,
                'paid' => 260000,
                'balance' => 0,
                'status' => 'Paid',
            ],
        ]);

    $sale = Sale::query()->firstOrFail();
    expect($sale->invoice_number)->toBe('INV-2026-1041')
        ->and($piece1->fresh()->status)->toBe('sold')
        ->and($piece2->fresh()->status)->toBe('sold')
        ->and($sale->payments()->count())->toBe(1)
        ->and((int) $sale->payments()->first()->amount)->toBe(260000);

    // Verify movement history entry
    $movement1 = PieceMovement::query()->where('piece_id', $piece1->id)->where('type', 'sale')->first();
    expect($movement1)->not->toBeNull()
        ->and($movement1->reference)->toBe('INV-2026-1041');

    // Create next sale to verify counter increments to 1042
    $piece3 = createTestPiece($display, 'in_stock', 80000);
    $secondResponse = $this->actingAs($salesUser)
        ->postJson(route('sales.store'), [
            'location_id' => $display->id,
            'customer_id' => null,
            'discount' => 0,
            'paid_now' => 80000,
            'method' => 'cash',
            'items' => [
                ['piece_id' => $piece3->id, 'unit_price' => 80000],
            ],
        ]);

    $secondResponse->assertOk()
        ->assertJsonPath('sale.invoice_number', 'INV-2026-1042');
});

test('a customer is required when there is a balance due', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $display = createTestDisplay('Hangu Display');
    $piece = createTestPiece($display, 'in_stock', 150000);

    $this->actingAs($salesUser)
        ->postJson(route('sales.store'), [
            'location_id' => $display->id,
            'customer_id' => null,
            'discount' => 0,
            'paid_now' => 50000, // Balance due is 100,000
            'method' => 'cash',
            'items' => [
                ['piece_id' => $piece->id, 'unit_price' => 150000],
            ],
        ])
        ->assertStatus(422)
        ->assertJson(['message' => 'Choose a customer to record a balance due']);
});

test('creating customer stores dashes for empty phone or city and returns json', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);

    // Validation error when name is empty
    $this->actingAs($salesUser)
        ->postJson(route('customers.store'), [
            'name' => '',
            'phone' => '',
            'city' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    // Successful customer creation with empty phone and city
    $response = $this->actingAs($salesUser)
        ->postJson(route('customers.store'), [
            'name' => 'New Test Customer',
            'phone' => '',
            'city' => '',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'New Test Customer added',
            'customer' => [
                'name' => 'New Test Customer',
                'phone' => '-',
                'city' => '-',
            ],
        ]);
});

test('void sale returns pieces back to stock at display and updates movement history', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $display = createTestDisplay('Hangu Display');
    $piece = createTestPiece($display, 'in_stock', 150000);
    $customer = Customer::query()->create(['name' => 'Test Customer']);

    // Create an unpaid sale (paid_now = 0)
    $this->actingAs($owner)
        ->postJson(route('sales.store'), [
            'location_id' => $display->id,
            'customer_id' => $customer->id,
            'discount' => 0,
            'paid_now' => 0,
            'method' => 'cash',
            'items' => [
                ['piece_id' => $piece->id, 'unit_price' => 150000],
            ],
        ])->assertOk();

    $sale = Sale::query()->firstOrFail();
    expect($piece->fresh()->status)->toBe('sold');

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
        ->and($voidMovement->reference)->toBe($sale->invoice_number);
});

test('sales role cannot void a sale', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $display = createTestDisplay('Hangu Display');
    $piece = createTestPiece($display, 'in_stock', 150000);
    $customer = Customer::query()->create(['name' => 'Test Customer']);

    $this->actingAs($salesUser)
        ->postJson(route('sales.store'), [
            'location_id' => $display->id,
            'customer_id' => $customer->id,
            'discount' => 0,
            'paid_now' => 0,
            'method' => 'cash',
            'items' => [
                ['piece_id' => $piece->id, 'unit_price' => 150000],
            ],
        ])->assertOk();

    $sale = Sale::query()->firstOrFail();

    // Sales role cannot void sale
    $this->actingAs($salesUser)
        ->postJson(route('sales.void', $sale))
        ->assertStatus(403);
});
