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
    $piece = app(PieceService::class)
        ->receive($carpet, $location, $user, 1, 70000, 70000, 0, 0, 0, 0)
        ->firstOrFail();
    if ($price !== null) {
        $piece->update(['price' => $price]);
    }

    return $piece->fresh();
}

test('piece price defaults to carpet list price unless its own price is set', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Hangu Display');

    $listPricedPiece = receiveTestPiece($carpet, $location, $owner);
    $ownPricedPiece = receiveTestPiece($carpet, $location, $owner, 170000);

    expect($listPricedPiece->effectivePrice())->toBe(185000)
        ->and($ownPricedPiece->effectivePrice())->toBe(170000)
        ->and($listPricedPiece->code)->toBe('CP-'.str_pad((string) $listPricedPiece->id, 6, '0', STR_PAD_LEFT))
        ->and(PieceMovement::query()->where('piece_id', $listPricedPiece->id)->value('type'))->toBe('receive');

    $this->actingAs($owner)
        ->get(route('new-sale.index', ['piece_id' => $ownPricedPiece->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece_id', $ownPricedPiece->id)
            ->where('pieces.1.price', 170000));
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
        ->and($sale->items()->firstOrFail()->piece_code)->toBe($piece->code)
        ->and($sale->items()->firstOrFail()->name)->toBe('Test Herati 6x9 ft')
        ->and($sale->items()->firstOrFail()->unit_cost)->toBe(70000)
        ->and(PieceMovement::query()->where('piece_id', $piece->id)->orderBy('id')->pluck('type')->all())
        ->toBe(['receive', 'sale']);

    $saleMovement = PieceMovement::query()
        ->where('piece_id', $piece->id)
        ->where('type', 'sale')
        ->firstOrFail();
    expect($saleMovement->from_location_id)->toBe($display->id)
        ->and($saleMovement->to_location_id)->toBeNull()
        ->and($saleMovement->reference)->toBe($sale->invoice_number)
        ->and($saleMovement->user_id)->toBe($owner->id)
        ->and($saleMovement->moved_at->toIso8601String())->toBe($sale->sold_at->toIso8601String());
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

    $voidMovement = PieceMovement::query()
        ->where('piece_id', $firstPiece->id)
        ->where('type', 'sale_void')
        ->firstOrFail();
    expect($voidMovement->from_location_id)->toBeNull()
        ->and($voidMovement->to_location_id)->toBe($display->id)
        ->and($voidMovement->user_id)->toBe($owner->id);

    $this->actingAs($owner)
        ->get(route('pieces.index', ['piece_code' => $firstPiece->code]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece.history.0.text', "Sale voided, back in stock at {$display->name} ({$sale->invoice_number})")
            ->where('selected_piece.history.1.text', "Sold at {$display->name} ({$sale->invoice_number})")
            ->where('selected_piece.history.2.text', "Received into {$display->name} ()"));
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
            ->where('not_found_message', 'No piece with ID CP-999999'));
});

test('receiving calculates landed piece costs and chooses grade from the generated ID', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $destination = piecesTestLocation('Receiving Display');

    $pieces = app(PieceService::class)->receive(
        $carpet,
        $destination,
        $owner,
        3,
        100000,
        1000000,
        100000,
        50000,
        50000,
        50000,
        'CN-123',
    );

    expect($pieces)->toHaveCount(3);
    foreach ($pieces as $piece) {
        expect($piece->landed_cost)->toBe(125000)
            ->and($piece->price)->toBeNull()
            ->and($piece->status)->toBe('in_stock')
            ->and($piece->location_id)->toBe($destination->id)
            ->and($piece->grade)->toBe(match ($piece->id % 3) {
                0, 1 => 'A',
                2 => 'B',
            });

        $movement = $piece->movements()->firstOrFail();
        expect($movement->type)->toBe('receive')
            ->and($movement->from_location_id)->toBeNull()
            ->and($movement->to_location_id)->toBe($destination->id)
            ->and($movement->reference)->toBe('CN-123')
            ->and($movement->user_id)->toBe($owner->id);
    }

    $zeroGoodsPiece = app(PieceService::class)
        ->receive($carpet, $destination, $owner, 1, 100000, 0, 100000, 50000, 50000, 50000)
        ->firstOrFail();
    expect($zeroGoodsPiece->landed_cost)->toBe(100000);
});

test('piece code for ID 3617 is CP-003617', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Code Display');

    $piece = Piece::query()->forceCreate([
        'id' => 3617,
        'code' => null,
        'carpet_id' => $carpet->id,
        'location_id' => $location->id,
        'status' => 'in_stock',
        'grade' => 'B',
        'landed_cost' => 100000,
        'received_at' => now(),
    ]);

    expect($piece->code)->toBe('CP-003617')
        ->and(Piece::normalizeCode('3617'))->toBe('CP-003617')
        ->and(Piece::normalizeCode('CP-003617'))->toBe('CP-003617');
});

test('piece search matches code name and design without case sensitivity', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet(['design' => 'Mina']);
    $location = piecesTestLocation('Search Display');
    $piece = receiveTestPiece($carpet, $location, $owner);

    foreach ([$piece->code, 'TEST HERATI', 'mInA'] as $search) {
        $this->actingAs($owner)
            ->get(route('pieces.index', ['search' => $search]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pieces', 1)
                ->where('pieces.0.code', $piece->code));
    }
});

test('admin and sales can view pieces but cannot receive landed costs', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Permission Display');
    $piece = receiveTestPiece($carpet, $location, $owner);

    foreach ([UserRole::ADMIN, UserRole::SALES] as $role) {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)
            ->get(route('pieces.index', ['piece_code' => $piece->code]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_view_costs', false)
                ->where('pieces.0.landed_cost', null)
                ->where('selected_piece.landed_cost', null));
    }
});

test('pieces page denies users without stock view permission', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $this->app['config']->set('roles.sales', ['sales.create', 'sales.view']);

    $this->actingAs($salesUser)
        ->get(route('pieces.index'))
        ->assertForbidden();
});

test('new sale ID lookup returns the required piece validation messages', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $firstDisplay = piecesTestLocation('First Display');
    $secondDisplay = piecesTestLocation('Second Display');
    $piece = receiveTestPiece($carpet, $firstDisplay, $owner);

    $this->actingAs($owner)
        ->getJson(route('pieces.lookup', ['code' => '999999', 'location_id' => $firstDisplay->id]))
        ->assertNotFound()
        ->assertJsonPath('message', 'No piece with ID CP-999999');

    foreach ([
        'in_transit' => 'in transit',
        'sold' => 'sold',
        'damaged' => 'damaged',
        'reserved' => 'reserved',
    ] as $status => $statusLabel) {
        $piece->update(['status' => $status]);
        $this->getJson(route('pieces.lookup', ['code' => $piece->code, 'location_id' => $firstDisplay->id]))
            ->assertUnprocessable()
            ->assertJsonPath('message', "{$piece->code} is {$statusLabel} and can't be sold");
    }

    $piece->update(['status' => 'in_stock']);
    $this->getJson(route('pieces.lookup', ['code' => $piece->code, 'location_id' => $firstDisplay->id]))
        ->assertOk()
        ->assertJsonPath('piece.code', $piece->code)
        ->assertJsonPath('piece.price', 185000);

    $this->getJson(route('pieces.lookup', ['code' => $piece->code, 'location_id' => $secondDisplay->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', "{$piece->code} is at {$firstDisplay->name}, not the selected display");

    $this->getJson(route('pieces.lookup', [
        'code' => $piece->code,
        'location_id' => $firstDisplay->id,
        'existing_piece_ids' => [$piece->id],
    ]))
        ->assertUnprocessable()
        ->assertJsonPath('message', "{$piece->code} is already on the invoice");
});

test('transfer movement types preserve source and destination history', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $source = piecesTestLocation('Transfer Source', 'warehouse');
    $destination = piecesTestLocation('Transfer Destination', 'display');
    $piece = app(PieceService::class)
        ->receive($carpet, $source, $owner, 1, 70000, 70000, 0, 0, 0, 0, 'CN-1')
        ->firstOrFail();

    app(PieceService::class)->changeStatus($piece, 'transfer_out', 'in_transit', $destination, 'TR-1', $owner);
    expect($piece->fresh()->status)->toBe('in_transit')
        ->and($piece->fresh()->location_id)->toBe($source->id);

    app(PieceService::class)->changeStatus($piece, 'transfer_in', 'in_stock', $destination, 'TR-2', $owner);
    expect($piece->fresh()->status)->toBe('in_stock')
        ->and($piece->fresh()->location_id)->toBe($destination->id);

    $this->actingAs($owner)
        ->get(route('pieces.index', ['piece_code' => $piece->code]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece.history.0.text', "Arrived at {$destination->name} (TR-2)")
            ->where('selected_piece.history.1.text', "Sent from {$source->name} to {$destination->name} (TR-1)")
            ->where('selected_piece.history.2.text', "Received into {$source->name} (CN-1)"));
});

test('pieces page caps the displayed rows at eighty', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Eighty Row Display');
    app(PieceService::class)->receive(
        $carpet,
        $location,
        $owner,
        81,
        70000,
        70000,
        0,
        0,
        0,
        0,
    );

    $this->actingAs($owner)
        ->get(route('pieces.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('pieces', 80)
            ->where('total', 81));
});

test('pieces are listed in ascending ID order', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Ascending Display');
    $first = receiveTestPiece($carpet, $location, $owner);
    $second = receiveTestPiece($carpet, $location, $owner);
    $third = receiveTestPiece($carpet, $location, $owner);

    $this->actingAs($owner)
        ->get(route('pieces.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pieces.0.id', $first->id)
            ->where('pieces.1.id', $second->id)
            ->where('pieces.2.id', $third->id));
});

test('new sale only offers active display locations and a selected piece sets its sale defaults', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $activeDisplay = piecesTestLocation('Active Display');
    $inactiveDisplay = piecesTestLocation('Inactive Display');
    $inactiveDisplay->update(['active' => false]);
    piecesTestLocation('Warehouse', 'warehouse');
    $piece = receiveTestPiece($carpet, $activeDisplay, $owner, 170000);

    $this->actingAs($owner)
        ->get(route('new-sale.index', ['piece_id' => $piece->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('locations', [['id' => $activeDisplay->id, 'name' => $activeDisplay->name]])
            ->where('selected_piece_id', $piece->id)
            ->where('pieces.0.location_id', $activeDisplay->id)
            ->where('pieces.0.price', 170000));
});

test('top bar search normalizes IDs and checks not found before stock permission', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $carpet = piecesTestCarpet();
    $location = piecesTestLocation('Top Search Display');
    $piece = receiveTestPiece($carpet, $location, $owner);

    $this->actingAs($salesUser);
    $this->app['config']->set('roles.sales', ['sales.create', 'sales.view']);
    $this->getJson(route('pieces.search', ['code' => '3617']))
        ->assertNotFound()
        ->assertJsonPath('message', 'No piece with ID CP-003617');

    $piece->update(['code' => 'CP-003617']);
    $this->getJson(route('pieces.search', ['code' => '3617']))
        ->assertForbidden()
        ->assertJsonPath('message', "You don't have access to piece records");

    $this->app['config']->set('roles.owner', ['stock.view']);
    $this->actingAs($owner)
        ->getJson(route('pieces.search', ['code' => 'CP-003617']))
        ->assertOk()
        ->assertJsonPath('url', route('pieces.index', ['piece_code' => 'CP-003617']));
});
