<?php

use App\Models\Carpet;
use App\Models\User;
use App\UserRole;
use Database\Seeders\CarpetSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('owners and admins see the seeded carpets in creation order', function (UserRole $role) {
    app(CarpetSeeder::class)->run();
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user)
        ->get(route('carpets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('carpets/index')
            ->where('canManage', true)
            ->has('carpets', 8)
            ->where('carpets.0.sku', 'TBZ-6x9-IND')
            ->where('carpets.0.name', 'Tabriz Herati')
            ->where('carpets.0.in_stock_count', 0)
            ->where('carpets.1.sku', 'KSN-4x6-RED')
            ->where('carpets.2.sku', 'QOM-5x8-IVR')
            ->where('carpets.3.sku', 'ISF-8x10-NVY')
            ->where('carpets.4.sku', 'NAN-3x5-CRM')
            ->where('carpets.5.sku', 'BKH-6x9-RST')
            ->where('carpets.6.sku', 'MSH-9x12-RED')
            ->where('carpets.7.sku', 'MCH-5x8-GRY')
            ->where('carpets.7.design', 'Machine made')
            ->where('carpets.7.weave', 'Machine made'),
        );
})->with([
    'owner' => [UserRole::OWNER],
    'admin' => [UserRole::ADMIN],
]);

test('sales can view the carpet list but cannot create a carpet', function () {
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($sales)
        ->get(route('carpets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('carpets/index')
            ->where('canManage', false),
        );

    $this->post(route('carpets.store'), ['name' => 'Forbidden Carpet'])
        ->assertForbidden();

    expect(Carpet::query()->count())->toBe(0);
});

test('users without catalog view permission are forbidden from the catalog', function () {
    config()->set('roles.sales', []);
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($sales)
        ->get(route('carpets.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/page')
            ->where('status', 403)
            ->where('pageLabel', 'Carpets'),
        );
});

test('guests are redirected to login from the carpet list and create route', function () {
    $this->get(route('carpets.index'))
        ->assertRedirect(route('login'));

    $this->post(route('carpets.store'), ['name' => 'Guest Carpet'])
        ->assertRedirect(route('login'));
});

test('a carpet is created with all trimmed fields and a generated sku', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->post(route('carpets.store'), [
            'name' => ' Tabriz Herati ',
            'design' => ' Tabriz ',
            'size' => ' 6x9 ft ',
            'color' => ' Indigo ',
            'material' => ' Wool and silk ',
            'price' => '185000.4',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('carpets.index'));

    $carpet = Carpet::query()->firstOrFail();

    expect($carpet->sku)->toBe('TAB-6x9ft-IND')
        ->and($carpet->name)->toBe('Tabriz Herati')
        ->and($carpet->design)->toBe('Tabriz')
        ->and($carpet->weave)->toBe('Handmade')
        ->and($carpet->size)->toBe('6x9 ft')
        ->and($carpet->color)->toBe('Indigo')
        ->and($carpet->material)->toBe('Wool and silk')
        ->and($carpet->price)->toBe(185000);
});

test('carpet fields receive the required defaults and the sku uses NA for empty size and color', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->post(route('carpets.store'), ['name' => 'Kerman Rug'])
        ->assertSessionHasNoErrors();

    $carpet = Carpet::query()->firstOrFail();

    expect($carpet->sku)->toBe('KER-NA-NA')
        ->and($carpet->design)->toBe('Kerman')
        ->and($carpet->material)->toBe('Wool')
        ->and($carpet->size)->toBe('-')
        ->and($carpet->color)->toBe('-')
        ->and($carpet->price)->toBe(0)
        ->and($carpet->weave)->toBe('Handmade');
});

test('the sku formula removes size whitespace and uses the first three design and color characters', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->post(route('carpets.store'), [
            'name' => '  Kashan Medallion ',
            'design' => ' Kashan ',
            'size' => ' 4 x 6 ft ',
            'color' => ' Crimson ',
        ])
        ->assertSessionHasNoErrors();

    expect(Carpet::query()->firstOrFail()->sku)->toBe('KAS-4x6ft-CRI');
});

test('carpet name is required after trimming', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    foreach (['', '   '] as $name) {
        $this->actingAs($owner)
            ->from(route('carpets.index'))
            ->post(route('carpets.store'), ['name' => $name])
            ->assertSessionHasErrors('name');

        expect(session('errors')->first('name'))->toBe('Enter a name');
    }

    expect(Carpet::query()->count())->toBe(0);
});

test('negative carpet prices are rejected', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->from(route('carpets.index'))
        ->post(route('carpets.store'), [
            'name' => 'Negative Price Carpet',
            'price' => '-1',
        ])
        ->assertSessionHasErrors('price');

    expect(Carpet::query()->count())->toBe(0);
});

test('users without catalog view permission do not receive the carpets sidebar permission', function () {
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($sales)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', ['catalog.view', 'stock.view', 'sales.create', 'sales.view']),
        );

    config()->set('roles.sales', []);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', []),
        );
});

test('the carpet seeder can run repeatedly without duplicate sku rows', function () {
    $seeder = app(CarpetSeeder::class);
    $seeder->run();
    $seeder->run();

    expect(Carpet::query()->count())->toBe(8)
        ->and(Carpet::query()->orderBy('created_at')->orderBy('id')->pluck('sku')->all())
        ->toBe([
            'TBZ-6x9-IND',
            'KSN-4x6-RED',
            'QOM-5x8-IVR',
            'ISF-8x10-NVY',
            'NAN-3x5-CRM',
            'BKH-6x9-RST',
            'MSH-9x12-RED',
            'MCH-5x8-GRY',
        ]);
});

test('different carpets may share the same generated sku', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $attributes = [
        'name' => 'Kerman Rug',
        'size' => '6x9 ft',
        'color' => 'Indigo',
    ];

    $this->actingAs($owner)
        ->post(route('carpets.store'), $attributes)
        ->assertSessionHasNoErrors();

    $this->post(route('carpets.store'), [
        ...$attributes,
        'name' => 'Another Kerman Rug',
        'design' => 'Kerman',
    ])->assertSessionHasNoErrors();

    expect(Carpet::query()->count())->toBe(2)
        ->and(Carpet::query()->pluck('sku')->unique()->all())
        ->toBe(['KER-6x9ft-IND']);
});
