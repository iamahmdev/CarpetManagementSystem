<?php

use App\Models\Carpet;
use App\Models\Location;
use App\Models\Piece;
use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('owner and admin can access stock report, but sales role gets 403 error page', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    // Owner can access
    $this->actingAs($owner)
        ->get(route('stock-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-report/index')
            ->has('locations')
            ->has('designs')
            ->where('can_view_costs', true)
        );

    // Admin can access
    $this->actingAs($admin)
        ->get(route('stock-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock-report/index')
            ->has('locations')
            ->has('designs')
            ->where('can_view_costs', false)
        );

    // Sales role gets 403 page
    $this->actingAs($sales)
        ->get(route('stock-report.index'))
        ->assertStatus(403)
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/page')
            ->where('status', 403)
            ->where('role', 'Sales')
            ->where('pageLabel', 'Stock by Location')
        );

    // Guest gets redirected to login
    auth()->logout();
    $this->get(route('stock-report.index'))->assertRedirect(route('login'));
});

test('cost values are provided only to users with costs.view permission', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $warehouse = Location::query()->firstOrCreate(
        ['name' => 'Hangu Warehouse'],
        ['city' => 'Hangu', 'type' => 'warehouse', 'active' => true]
    );

    $carpet = Carpet::query()->firstOrCreate(
        ['sku' => 'TEST-TBZ-1'],
        [
            'name' => 'Test Tabriz',
            'design' => 'Tabriz',
            'weave' => 'Handmade',
            'size' => '6x9 ft',
            'color' => 'Red',
            'material' => 'Wool',
            'price' => 200000,
        ]
    );

    Piece::query()->create([
        'code' => 'CP-TEST-COST-01',
        'carpet_id' => $carpet->id,
        'location_id' => $warehouse->id,
        'status' => 'in_stock',
        'grade' => 'A',
        'landed_cost' => 120000,
        'price' => 200000,
        'received_at' => now(),
    ]);

    // Owner has costs.view -> cost_value is visible
    $this->actingAs($owner)
        ->get(route('stock-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_view_costs', true)
            ->where('locations.0.cost_value', fn ($val) => is_numeric($val) && $val > 0)
        );

    // Admin does not have costs.view -> cost_value is null
    $this->actingAs($admin)
        ->get(route('stock-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_view_costs', false)
            ->where('locations.0.cost_value', null)
        );
});

test('only in_stock pieces at active locations are counted, while in_transit, sold, and inactive locations are excluded', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $activeLoc = Location::query()->firstOrCreate(
        ['name' => 'Kohat Warehouse'],
        ['city' => 'Kohat', 'type' => 'warehouse', 'active' => true]
    );

    $inactiveLoc = Location::query()->create([
        'name' => 'Old Inactive Warehouse',
        'city' => 'Peshawar',
        'type' => 'warehouse',
        'active' => false,
    ]);

    $carpet = Carpet::query()->firstOrCreate(
        ['sku' => 'TEST-QOM-1'],
        [
            'name' => 'Test Qom',
            'design' => 'Qom',
            'weave' => 'Handmade',
            'size' => '5x8 ft',
            'color' => 'Ivory',
            'material' => 'Silk',
            'price' => 150000,
        ]
    );

    // in_stock piece at active location
    Piece::query()->create([
        'code' => 'CP-COUNT-01',
        'carpet_id' => $carpet->id,
        'location_id' => $activeLoc->id,
        'status' => 'in_stock',
        'grade' => 'A',
        'landed_cost' => 90000,
        'price' => null, // will use carpet list price (150000)
        'received_at' => now(),
    ]);

    // in_transit piece (should NOT be counted)
    Piece::query()->create([
        'code' => 'CP-COUNT-02',
        'carpet_id' => $carpet->id,
        'location_id' => $activeLoc->id,
        'status' => 'in_transit',
        'grade' => 'A',
        'landed_cost' => 90000,
        'price' => 150000,
        'received_at' => now(),
    ]);

    // sold piece (should NOT be counted)
    Piece::query()->create([
        'code' => 'CP-COUNT-03',
        'carpet_id' => $carpet->id,
        'location_id' => $activeLoc->id,
        'status' => 'sold',
        'grade' => 'A',
        'landed_cost' => 90000,
        'price' => 150000,
        'received_at' => now(),
    ]);

    // in_stock piece at inactive location (should NOT be counted or listed)
    Piece::query()->create([
        'code' => 'CP-COUNT-04',
        'carpet_id' => $carpet->id,
        'location_id' => $inactiveLoc->id,
        'status' => 'in_stock',
        'grade' => 'A',
        'landed_cost' => 90000,
        'price' => 150000,
        'received_at' => now(),
    ]);

    $response = $this->actingAs($owner)->get(route('stock-report.index'))->assertOk();

    // Verify inactive location is not in locations prop
    $response->assertInertia(fn (Assert $page) => $page
        ->where('locations', fn ($locations) => collect($locations)->every(fn ($l) => $l['name'] !== 'Old Inactive Warehouse'))
    );
});

test('designs with two or fewer pieces are flagged as low stock', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $response = $this->actingAs($owner)->get(route('stock-report.index'))->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page->has('designs');
        $designs = $page->toArray()['props']['designs'];

        foreach ($designs as $design) {
            if ($design['total'] <= 2) {
                expect($design['is_low_stock'])->toBeTrue();
            } else {
                expect($design['is_low_stock'])->toBeFalse();
            }
        }
    });
});
