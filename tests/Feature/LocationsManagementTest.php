<?php

use App\Models\Location;
use App\Models\User;
use App\UserRole;
use Database\Seeders\LocationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('owners see the seeded locations in creation order', function () {
    app(LocationSeeder::class)->run();
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->get(route('locations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('locations/index')
            ->has('locations', 4)
            ->where('locations.0.name', 'Hangu Warehouse')
            ->where('locations.1.name', 'Kohat Warehouse')
            ->where('locations.2.name', 'Hangu Display')
            ->where('locations.2.adjacent_warehouse', 'Hangu Warehouse')
            ->where('locations.3.name', 'Kohat Display')
            ->where('locations.3.adjacent_warehouse', 'Kohat Warehouse')
            ->where('locations.0.pieces_count', 0),
        );
});

test('inactive locations remain visible in the locations list', function () {
    $this->seed(LocationSeeder::class);
    $inactive = Location::query()->where('name', 'Hangu Warehouse')->firstOrFail();
    $inactive->update(['active' => false]);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->get(route('locations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('locations', 4)
            ->where('locations.0.name', 'Hangu Warehouse'),
        );
});

test('guests are redirected to login for location routes', function () {
    $this->get(route('locations.index'))
        ->assertRedirect(route('login'));

    $this->post(route('locations.store'), [])
        ->assertRedirect(route('login'));
});

test('users without locations permission are forbidden on every location route', function () {
    foreach ([UserRole::ADMIN, UserRole::SALES] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('locations.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('errors/page')
                ->where('status', 403),
            );

        $this->post(route('locations.store'), [
            'name' => 'Forbidden Location',
            'type' => 'display',
            'city' => 'Peshawar',
        ])->assertForbidden();
    }
});

test('owners can create a display with an adjacent warehouse', function () {
    $this->seed(LocationSeeder::class);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $warehouse = Location::query()->where('name', 'Hangu Warehouse')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('locations.store'), [
            'name' => '  Peshawar Display ',
            'type' => 'display',
            'city' => ' Peshawar ',
            'parent_id' => $warehouse->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('locations.index'));

    $location = Location::query()->where('name', 'Peshawar Display')->firstOrFail();

    expect($location->city)->toBe('Peshawar')
        ->and($location->parent_id)->toBe($warehouse->id)
        ->and($location->active)->toBeTrue();
});

test('creating a warehouse ignores an adjacent warehouse', function () {
    $this->seed(LocationSeeder::class);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $warehouse = Location::query()->where('name', 'Hangu Warehouse')->firstOrFail();

    $this->actingAs($owner)
        ->post(route('locations.store'), [
            'name' => 'Peshawar Warehouse',
            'type' => 'warehouse',
            'city' => 'Peshawar',
            'parent_id' => $warehouse->id,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('locations.index'));

    expect(Location::query()->where('name', 'Peshawar Warehouse')->value('parent_id'))
        ->toBeNull();
});

test('location names are required after trimming', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    foreach (['', '   '] as $name) {
        $response = $this->actingAs($owner)
            ->from(route('locations.index'))
            ->post(route('locations.store'), [
                'name' => $name,
                'type' => 'display',
                'city' => 'Peshawar',
            ])
            ->assertSessionHasErrors('name');

        expect(session('errors')->first('name'))->toBe('Enter a name');
    }

    expect(Location::query()->count())->toBe(0);
});

test('location name and city must be strings', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->from(route('locations.index'))
        ->post(route('locations.store'), [
            'name' => ['not', 'a', 'string'],
            'type' => 'display',
            'city' => ['also', 'not', 'a', 'string'],
        ])
        ->assertSessionHasErrors(['name', 'city']);

    expect(Location::query()->count())->toBe(0);
});

test('an empty city is saved as a dash', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->post(route('locations.store'), [
            'name' => 'Peshawar Display',
            'type' => 'display',
            'city' => '   ',
        ])
        ->assertSessionHasNoErrors();

    expect(Location::query()->where('name', 'Peshawar Display')->value('city'))
        ->toBe('-');
});

test('an adjacent location must be a warehouse', function () {
    $this->seed(LocationSeeder::class);
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $display = Location::query()->where('name', 'Hangu Display')->firstOrFail();

    $this->actingAs($owner)
        ->from(route('locations.index'))
        ->post(route('locations.store'), [
            'name' => 'Peshawar Display',
            'type' => 'display',
            'city' => 'Peshawar',
            'parent_id' => $display->id,
        ])
        ->assertSessionHasErrors('parent_id');
});

test('the locations sidebar permission is owner only', function () {
    expect(config('permissions'))->toContain('locations.manage')
        ->and(config('roles.owner'))->toContain('locations.manage')
        ->and(config('roles.admin'))->not->toContain('locations.manage')
        ->and(config('roles.sales'))->not->toContain('locations.manage');

    $admin = User::factory()->create(['role' => UserRole::ADMIN]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', [
                'users.manage',
                'catalog.view',
                'catalog.manage',
                'stock.view',
                'containers.manage',
                'transfers.manage',
                'sales.create',
                'sales.view',
                'sales.void',
                'reports.receivables',
            ]),
        );
});

test('the locations seeder can run repeatedly without duplicates', function () {
    $seeder = app(LocationSeeder::class);
    $seeder->run();
    $seeder->run();

    expect(Location::query()->count())->toBe(4)
        ->and(Location::active()->count())->toBe(4)
        ->and(Location::warehouses()->count())->toBe(2)
        ->and(Location::displays()->count())->toBe(2);
});
