<?php

use App\Models\Carpet;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\Transfer;
use App\Models\User;
use App\UserRole;
use Database\Seeders\CarpetSeeder;
use Database\Seeders\ContainerSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\TransferSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function transfersTestLocation(string $name, string $type = 'warehouse', ?int $parentId = null): Location
{
    return Location::query()->create([
        'name' => $name,
        'type' => $type,
        'city' => 'Hangu',
        'parent_id' => $parentId,
        'active' => true,
    ]);
}

function transfersTestPiece(Location $location, Carpet $carpet, string $status = 'in_stock'): Piece
{
    $piece = Piece::query()->create([
        'carpet_id' => $carpet->id,
        'location_id' => $location->id,
        'status' => $status,
        'grade' => 'A',
        'landed_cost' => 50000,
        'price' => 100000,
        'received_at' => now(),
    ]);

    $piece->update(['code' => $piece->code]);

    return $piece;
}

test('only owner and admin can access transfers and sales receives 403', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($owner)->get(route('transfers.index'))->assertOk();
    $this->actingAs($admin)->get(route('transfers.index'))->assertOk();
    $this->actingAs($sales)->get(route('transfers.index'))->assertForbidden();
});

test('transfers page renders with transfers list and form dropdowns', function () {
    $this->seed([LocationSeeder::class, CarpetSeeder::class, ContainerSeeder::class, TransferSeeder::class]);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $response = $this->actingAs($owner)->get(route('transfers.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('transfers/index')
            ->has('transfers', 3)
            ->where('transfers.0.reference', 'TR-0003')
            ->where('transfers.0.status', 'in_transit')
            ->where('transfers.0.pieces_count', 3)
            ->where('transfers.1.reference', 'TR-0002')
            ->where('transfers.1.status', 'received')
            ->where('transfers.2.reference', 'TR-0001')
            ->where('transfers.2.status', 'received')
            ->has('warehouses')
            ->has('displays')
            ->has('pieces')
        );
});

test('creating a transfer requires at least one selected piece', function () {
    $warehouse = transfersTestLocation('Source Warehouse', 'warehouse');
    $display = transfersTestLocation('Target Display', 'display', $warehouse->id);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $response = $this->actingAs($owner)->post(route('transfers.store'), [
        'from_location_id' => $warehouse->id,
        'to_location_id' => $display->id,
        'piece_ids' => [],
    ]);

    $response->assertSessionHasErrors(['piece_ids' => 'Select at least one piece to send']);
});

test('creating a transfer marks pieces in transit without changing source location and adds history', function () {
    $warehouse = transfersTestLocation('Hangu WH', 'warehouse');
    $display = transfersTestLocation('Hangu Disp', 'display', $warehouse->id);
    $carpet = Carpet::query()->create([
        'sku' => 'TR-C1',
        'name' => 'Tabriz Herati',
        'design' => 'Herati',
        'weave' => 'Handmade',
        'material' => 'Wool',
        'color' => 'Navy',
        'size' => '6x9 ft',
        'price' => 120000,
    ]);

    $piece1 = transfersTestPiece($warehouse, $carpet);
    $piece2 = transfersTestPiece($warehouse, $carpet);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $response = $this->actingAs($owner)->post(route('transfers.store'), [
        'from_location_id' => $warehouse->id,
        'to_location_id' => $display->id,
        'piece_ids' => [$piece1->id, $piece2->id],
    ]);

    $response->assertRedirect(route('transfers.index'));

    $transfer = Transfer::query()->latest('id')->firstOrFail();
    expect($transfer->status)->toBe('in_transit')
        ->and($transfer->from_location_id)->toBe($warehouse->id)
        ->and($transfer->to_location_id)->toBe($display->id)
        ->and($transfer->pieces)->toHaveCount(2);

    $piece1->refresh();
    $piece2->refresh();

    expect($piece1->status)->toBe('in_transit')
        ->and($piece1->location_id)->toBe($warehouse->id)
        ->and($piece2->status)->toBe('in_transit')
        ->and($piece2->location_id)->toBe($warehouse->id);

    $movement = PieceMovement::query()
        ->where('piece_id', $piece1->id)
        ->where('type', 'transfer_out')
        ->firstOrFail();

    expect($movement->from_location_id)->toBe($warehouse->id)
        ->and($movement->to_location_id)->toBe($display->id)
        ->and($movement->reference)->toBe($transfer->reference);
});

test('marking a transfer received updates piece location and sets status to in_stock', function () {
    $warehouse = transfersTestLocation('Warehouse A', 'warehouse');
    $display = transfersTestLocation('Display A', 'display', $warehouse->id);
    $carpet = Carpet::query()->create([
        'sku' => 'TR-C2',
        'name' => 'Kashan Classic',
        'design' => 'Floral',
        'weave' => 'Handmade',
        'material' => 'Silk',
        'color' => 'Red',
        'size' => '8x10 ft',
        'price' => 200000,
    ]);

    $piece1 = transfersTestPiece($warehouse, $carpet);
    $piece2 = transfersTestPiece($warehouse, $carpet);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)->post(route('transfers.store'), [
        'from_location_id' => $warehouse->id,
        'to_location_id' => $display->id,
        'piece_ids' => [$piece1->id, $piece2->id],
    ]);

    $transfer = Transfer::query()->latest('id')->firstOrFail();

    $response = $this->actingAs($owner)->post(route('transfers.receive', $transfer));
    $response->assertRedirect(route('transfers.index'));

    $transfer->refresh();
    expect($transfer->status)->toBe('received')
        ->and($transfer->received_at)->not->toBeNull();

    $piece1->refresh();
    $piece2->refresh();

    expect($piece1->status)->toBe('in_stock')
        ->and($piece1->location_id)->toBe($display->id)
        ->and($piece2->status)->toBe('in_stock')
        ->and($piece2->location_id)->toBe($display->id);

    $inMovement = PieceMovement::query()
        ->where('piece_id', $piece1->id)
        ->where('type', 'transfer_in')
        ->firstOrFail();

    expect($inMovement->to_location_id)->toBe($display->id)
        ->and($inMovement->reference)->toBe($transfer->reference);
});

test('a piece in transit cannot be sold in new sale lookup', function () {
    $warehouse = transfersTestLocation('Warehouse B', 'warehouse');
    $display = transfersTestLocation('Display B', 'display', $warehouse->id);
    $carpet = Carpet::query()->create([
        'sku' => 'TR-C3',
        'name' => 'Nain Fine',
        'design' => 'Medallion',
        'weave' => 'Handmade',
        'material' => 'Wool',
        'color' => 'Beige',
        'size' => '5x7 ft',
        'price' => 85000,
    ]);

    $piece = transfersTestPiece($warehouse, $carpet);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)->post(route('transfers.store'), [
        'from_location_id' => $warehouse->id,
        'to_location_id' => $display->id,
        'piece_ids' => [$piece->id],
    ]);

    $piece->refresh();
    expect($piece->status)->toBe('in_transit');

    $response = $this->actingAs($owner)->getJson(route('pieces.lookup', [
        'code' => $piece->code,
        'location_id' => $display->id,
    ]));

    $response->assertStatus(422)
        ->assertJson(['message' => "{$piece->code} is in transit and can't be sold"]);
});

test('dashboard reflects pieces in transit count and pending transfers count', function () {
    $this->seed([LocationSeeder::class, CarpetSeeder::class, ContainerSeeder::class, TransferSeeder::class]);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $response = $this->actingAs($owner)->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('pieces_in_transit', 3)
            ->where('transfers_pending', 1)
        );
});
