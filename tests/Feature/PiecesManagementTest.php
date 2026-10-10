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
use Inertia\Testing\AssertableInertia as Assert;

function piecesTestCarpet(array $overrides = []): Carpet
{
    return Carpet::query()->create($overrides + [
        'sku' => 'TEST-6x9',
        'name' => 'Test Herati',
        'design' => 'Herati',
        'weave' => 'Hand knotted',
        'material' => 'Wool',
        'color' => 'Indigo',
        'size' => '6x9 ft',
        'price' => 185000,
    ]);
}

function piecesTestLocation(string $name, string $type = 'display'): Location
{
    return Location::query()->create([
        'name' => $name,
        'type' => $type,
        'city' => 'Hangu',
        'active' => true,
    ]);
}

function receiveTestPiece(Carpet $carpet, Location $location, User $user, ?int $price = null): Piece
{
    return app(PieceService::class)
        ->receive($carpet, $location, $user, 1, 'A', 70000, $price)
        ->firstOrFail();
}

test('piece price defaults to carpet list price unless its own price is set', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Hangu Display');

    $listPricedPiece = receiveTestPiece($carpet, $location, $owner);
    $ownPricedPiece = receiveTestPiece($carpet, $location, $owner, 199000);

    expect($listPricedPiece->effectivePrice())->toBe(185000)
        ->and($ownPricedPiece->effectivePrice())->toBe(199000)
        ->and($listPricedPiece->code)->toBe('CP-'.str_pad((string) $listPricedPiece->id, 6, '0', STR_PAD_LEFT))
        ->and(PieceMovement::query()->where('piece_id', $listPricedPiece->id)->value('type'))->toBe('receive');

    $this->actingAs($owner)
        ->get(route('new-sale.index', ['piece_id' => $ownPricedPiece->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece_id', $ownPricedPiece->id)
            ->where('pieces.1.price', 199000));
});

test('sale marks the selected in-stock piece sold and records its movement', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $display = piecesTestLocation('Hangu Display');
    $piece = receiveTestPiece($carpet, $display, $owner);
    $customer = Customer::query()->create(['name' => 'Piece Sale Buyer']);

    $this->actingAs($owner)->post(route('sales.store'), [
        'customer_id' => $customer->id,
        'location_id' => $display->id,
        'discount' => 0,
        'paid_now' => 0,
        'method' => 'cash',
        'items' => [['piece_id' => $piece->id, 'unit_price' => $piece->effectivePrice()]],
    ])->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    expect($piece->fresh()->status)->toBe('sold')
        ->and($sale->items()->firstOrFail()->piece_id)->toBe($piece->id)
        ->and(PieceMovement::query()->where('piece_id', $piece->id)->orderBy('id')->pluck('type')->all())
        ->toBe(['receive', 'sale']);
});

test('only in-stock pieces at the selected display can be sold', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $firstDisplay = piecesTestLocation('Hangu Display');
    $otherDisplay = piecesTestLocation('Kohat Display');
    $piece = receiveTestPiece($carpet, $firstDisplay, $owner);

    $payload = [
        'location_id' => $otherDisplay->id,
        'paid_now' => 185000,
        'method' => 'cash',
        'items' => [['piece_id' => $piece->id, 'unit_price' => 185000]],
    ];
    $this->actingAs($owner)->post(route('sales.store'), $payload)
        ->assertSessionHasErrors('items');
    expect(Sale::query()->count())->toBe(0)
        ->and($piece->fresh()->status)->toBe('in_stock');

    $piece->update(['status' => 'reserved']);
    $payload['location_id'] = $firstDisplay->id;
    $this->post(route('sales.store'), $payload)->assertSessionHasErrors('items');
    expect(Sale::query()->count())->toBe(0);
});

test('voiding a sale returns every piece to its sale location with sale_void movements', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $display = piecesTestLocation('Hangu Display');
    $firstPiece = receiveTestPiece($carpet, $display, $owner);
    $secondPiece = receiveTestPiece($carpet, $display, $owner);
    $customer = Customer::query()->create(['name' => 'Void Sale Buyer']);

    $this->actingAs($owner)->post(route('sales.store'), [
        'customer_id' => $customer->id,
        'location_id' => $display->id,
        'discount' => 0,
        'paid_now' => 0,
        'method' => 'cash',
        'items' => [
            ['piece_id' => $firstPiece->id, 'unit_price' => 185000],
            ['piece_id' => $secondPiece->id, 'unit_price' => 185000],
        ],
    ])->assertRedirect();

    $sale = Sale::query()->firstOrFail();
    expect($firstPiece->fresh()->status)->toBe('sold')
        ->and($secondPiece->fresh()->status)->toBe('sold');

    $this->post(route('sales.void', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('void')
        ->and($firstPiece->fresh()->status)->toBe('in_stock')
        ->and($secondPiece->fresh()->status)->toBe('in_stock')
        ->and($firstPiece->fresh()->location_id)->toBe($display->id)
        ->and($secondPiece->fresh()->location_id)->toBe($display->id)
        ->and(PieceMovement::query()->where('type', 'sale_void')->count())->toBe(2)
        ->and(PieceMovement::query()
            ->where('piece_id', $firstPiece->id)
            ->where('type', 'sale_void')
            ->value('reference'))->toBe($sale->invoice_number);
});

test('piece ID lookup accepts prefixed and unprefixed codes and hides cost from sales users', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $carpet = piecesTestCarpet();
    $display = piecesTestLocation('Hangu Display');
    $piece = receiveTestPiece($carpet, $display, $owner);

    $this->actingAs($salesUser)
        ->get(route('pieces.index', ['piece_code' => (string) $piece->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece.code', $piece->code)
            ->where('selected_piece.landed_cost', null)
            ->where('not_found_message', null));

    $this->actingAs($owner)->get(route('pieces.index', ['piece_code' => $piece->code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece.code', $piece->code)
            ->where('selected_piece.landed_cost', 70000));

    $this->get(route('pieces.index', ['piece_code' => '999999']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('not_found_message', 'No piece with ID CP-999999.'));
});
