<?php

use App\Models\Carpet;
use App\Models\Container;
use App\Models\ContainerItem;
use App\Models\Location;
use App\Models\Piece;
use App\Models\PieceMovement;
use App\Models\Sale;
use App\Models\User;
use App\Services\PieceService;
use App\UserRole;
use Database\Seeders\CarpetSeeder;
use Database\Seeders\ContainerSeeder;
use Database\Seeders\LocationSeeder;
use Inertia\Testing\AssertableInertia as Assert;

function containersTestLocation(string $name, string $type = 'warehouse'): Location
{
    return Location::query()->create([
        'name' => $name,
        'type' => $type,
        'city' => 'Hangu',
        'active' => true,
    ]);
}

function containersTestCarpet(string $name = 'Container Carpet', string $size = '6x9 ft'): Carpet
{
    static $sequence = 0;
    $sequence++;

    return Carpet::query()->create([
        'sku' => 'CT-'.$sequence,
        'name' => $name,
        'design' => 'Test design',
        'weave' => 'Handmade',
        'material' => 'Wool',
        'color' => 'Indigo',
        'size' => $size,
        'price' => 185000,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function containersTestRecord(Location $destination, array $overrides = []): Container
{
    return Container::query()->create($overrides + [
        'number' => 'IR-TEST-001',
        'supplier' => 'Test Supplier',
        'destination_id' => $destination->id,
        'status' => 'arrived',
        'eta' => now()->toDateString(),
        'arrived_at' => now(),
        'freight_cost' => 0,
        'customs_duty_cost' => 0,
        'clearing_cost' => 0,
        'transport_cost' => 0,
    ]);
}

function containersTestItem(
    Container $container,
    Carpet $carpet,
    int $quantity,
    int $unitPrice,
): ContainerItem {
    return $container->items()->create([
        'carpet_id' => $carpet->id,
        'expected_quantity' => $quantity,
        'unit_price' => $unitPrice,
        'received_quantity' => 0,
    ]);
}

test('only owner and admin can access container management and costs are owner only', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);
    $destination = containersTestLocation('Kohat Warehouse');
    $carpet = containersTestCarpet();
    $container = containersTestRecord($destination, ['number' => 'IR-ACCESS-001']);
    containersTestItem($container, $carpet, 2, 90000);

    $this->actingAs($owner)
        ->get(route('containers.index', ['container' => $container->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_view_costs', true)
            ->has('selected_container.costs', 5)
            ->where('selected_container.items.0.unit_price', 90000));

    $this->actingAs($admin)
        ->get(route('containers.index', ['container' => $container->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_view_costs', false)
            ->missing('selected_container.costs')
            ->missing('selected_container.total_landed_cost')
            ->missing('selected_container.items.0.unit_price')
            ->missing('selected_container.items.0.landed_cost_each'));

    $this->actingAs($sales)
        ->get(route('containers.index'))
        ->assertForbidden();
    $this->post(route('containers.arrive', $container))->assertForbidden();
    $this->post(route('containers.receive', $container), ['quantities' => []])
        ->assertForbidden();
});

test('containers page lists expected pieces newest ETA first and gates dashboard imports', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);
    $destination = containersTestLocation('Warehouse');
    $oldest = containersTestRecord($destination, [
        'number' => 'IR-OLD',
        'eta' => now()->subDays(2)->toDateString(),
    ]);
    $newest = containersTestRecord($destination, [
        'number' => 'IR-NEW',
        'eta' => now()->addDays(2)->toDateString(),
        'status' => 'on_the_way',
        'arrived_at' => null,
    ]);
    containersTestItem($oldest, containersTestCarpet(), 4, 5000);
    containersTestItem($newest, containersTestCarpet(), 6, 5000);

    $this->actingAs($owner)
        ->get(route('containers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('containers.0.number', 'IR-NEW')
            ->where('containers.0.eta_text', 'Due '.now()->addDays(2)->format('j M'))
            ->where('containers.0.expected_pieces', 6)
            ->where('containers.1.number', 'IR-OLD'));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_manage_containers', true)
            ->has('imports', 2));

    $this->actingAs($sales)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_manage_containers', false)
            ->has('imports', 0));
});

test('marking arrival only advances an on-the-way container', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Hangu Warehouse');
    $container = containersTestRecord($destination, [
        'number' => 'IR-ARRIVE-001',
        'status' => 'on_the_way',
        'arrived_at' => null,
    ]);

    $this->actingAs($owner)
        ->post(route('containers.arrive', $container))
        ->assertRedirect(route('containers.index'));
    expect($container->fresh()->status)->toBe('arrived')
        ->and($container->fresh()->arrived_at)->not->toBeNull();

    $this->post(route('containers.arrive', $container))
        ->assertUnprocessable();
    expect($container->fresh()->status)->toBe('arrived');
});

test('receiving an arrived container creates correctly costed pieces and labels', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Kohat Warehouse');
    $container = containersTestRecord($destination, [
        'number' => 'IR-2026-014',
        'freight_cost' => 250000,
        'customs_duty_cost' => 290000,
        'clearing_cost' => 55000,
        'transport_cost' => 45000,
    ]);
    $items = collect([
        containersTestItem($container, containersTestCarpet('Tabriz Herati'), 8, 90000),
        containersTestItem($container, containersTestCarpet('Isfahan Shah Abbasi'), 4, 150000),
        containersTestItem($container, containersTestCarpet('Mashhad Classic'), 4, 130000),
        containersTestItem($container, containersTestCarpet('Modern Machine Made'), 12, 15000),
    ]);

    $this->actingAs($owner)
        ->post(route('containers.receive', $container), [
            'quantities' => $items->mapWithKeys(
                fn (ContainerItem $item): array => [$item->id => $item->expected_quantity],
            )->all(),
        ])
        ->assertRedirect(route('containers.index', [
            'container' => $container->id,
            'labels' => 1,
        ]))
        ->assertSessionHas('success_message', '28 pieces received into Kohat Warehouse');

    $container->refresh();
    $pieces = Piece::query()->where('container_id', $container->id)->orderBy('id')->get();
    expect($container->status)->toBe('received')
        ->and($container->arrived_at)->not->toBeNull()
        ->and($pieces)->toHaveCount(28)
        ->and($pieces->first()->code)->toBe('CP-003600')
        ->and($pieces->last()->code)->toBe('CP-003627')
        ->and($pieces->first()->landed_cost)->toBe(118515)
        ->and($pieces->get(8)->landed_cost)->toBe(197525)
        ->and($pieces->get(12)->landed_cost)->toBe(171188)
        ->and($pieces->get(16)->landed_cost)->toBe(19752)
        ->and($pieces->every(fn (Piece $piece): bool => $piece->status === 'in_stock'
            && $piece->location_id === $destination->id
            && $piece->container_number === $container->number))
        ->toBeTrue()
        ->and(PieceMovement::query()->where('type', 'receive')->count())->toBe(28)
        ->and(PieceMovement::query()
            ->where('type', 'receive')
            ->where('reference', $container->number)
            ->where('user_id', $owner->id)
            ->count())->toBe(28)
        ->and($items[0]->fresh()->received_quantity)->toBe(8);

    $this->get(route('containers.index', [
        'container' => $container->id,
        'labels' => 1,
    ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('label_count', 28)
            ->has('label_pieces', 28));
});

test('receiving quantities clamps above expected and maps negative and blank values to zero', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Receive Warehouse');
    $container = containersTestRecord($destination, ['number' => 'IR-CLAMP-001']);
    $first = containersTestItem($container, containersTestCarpet('Above'), 2, 10000);
    $second = containersTestItem($container, containersTestCarpet('Negative'), 3, 10000);
    $third = containersTestItem($container, containersTestCarpet('Blank'), 4, 10000);

    $this->actingAs($owner)
        ->post(route('containers.receive', $container), [
            'quantities' => [
                $first->id => '99',
                $second->id => '-5',
                $third->id => '',
            ],
        ])
        ->assertSessionHas('success_message', '2 pieces received into Receive Warehouse');

    expect($first->fresh()->received_quantity)->toBe(2)
        ->and($second->fresh()->received_quantity)->toBe(0)
        ->and($third->fresh()->received_quantity)->toBe(0)
        ->and(Piece::query()->where('container_id', $container->id)->count())->toBe(2)
        ->and($container->fresh()->status)->toBe('received');
});

test('receiving rejects containers in the wrong status and received containers cannot be received twice', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Status Warehouse');
    $carpet = containersTestCarpet();
    $container = containersTestRecord($destination, [
        'number' => 'IR-STATUS-001',
        'status' => 'on_the_way',
    ]);
    $item = containersTestItem($container, $carpet, 1, 10000);
    $payload = ['quantities' => [$item->id => 1]];

    $this->actingAs($owner)
        ->post(route('containers.receive', $container), $payload)
        ->assertUnprocessable();
    $container->update(['status' => 'received']);
    $this->post(route('containers.receive', $container), $payload)
        ->assertUnprocessable();
    expect(Piece::query()->count())->toBe(0)
        ->and($container->fresh()->status)->toBe('received');
});

test('a failure during receiving rolls back pieces movements and status', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Rollback Warehouse');
    $container = containersTestRecord($destination, ['number' => 'IR-ROLLBACK-001']);
    $first = containersTestItem($container, containersTestCarpet('First'), 1, 10000);
    $second = containersTestItem($container, containersTestCarpet('Second'), 1, 10000);
    $realPieceService = app(PieceService::class);
    $calls = 0;
    $pieceService = Mockery::mock(PieceService::class);
    $pieceService->shouldReceive('receive')
        ->twice()
        ->andReturnUsing(function (...$arguments) use ($realPieceService, &$calls) {
            $calls++;
            if ($calls === 2) {
                throw new RuntimeException('Injected receiving failure.');
            }

            return $realPieceService->receive(...$arguments);
        });
    $this->app->instance(PieceService::class, $pieceService);

    $this->actingAs($owner)
        ->post(route('containers.receive', $container), [
            'quantities' => [$first->id => 1, $second->id => 1],
        ])
        ->assertServerError();

    expect(Piece::query()->count())->toBe(0)
        ->and(PieceMovement::query()->count())->toBe(0)
        ->and($container->fresh()->status)->toBe('arrived')
        ->and($first->fresh()->received_quantity)->toBe(0)
        ->and($second->fresh()->received_quantity)->toBe(0);
});

test('labels require a received container and piece records link to their real container', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Labels Warehouse');
    $carpet = containersTestCarpet();
    $container = containersTestRecord($destination, [
        'number' => 'IR-LABELS-001',
        'status' => 'received',
    ]);
    containersTestItem($container, $carpet, 1, 10000);
    $piece = app(PieceService::class)
        ->receive($carpet, $destination, $owner, 1, 10000, 10000, 0, 0, 0, 0, $container->number, $container->id)
        ->firstOrFail();

    $this->actingAs($owner)
        ->get(route('pieces.index', ['piece_code' => $piece->code]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected_piece.container', $container->number));

    $container->update(['status' => 'arrived']);
    $this->get(route('containers.index', [
        'container' => $container->id,
        'labels' => 1,
    ]))->assertUnprocessable();
});

test('container costs and expected piece counts match the approved seed data', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(CarpetSeeder::class);
    $this->seed(ContainerSeeder::class);

    $containers = Container::query()->with('items.carpet')->orderBy('number')->get()->keyBy('number');
    expect($containers)->toHaveCount(4)
        ->and($containers['IR-2026-012']->goodsCost())->toBe(1080000)
        ->and($containers['IR-2026-012']->totalLandedCost())->toBe(1500000)
        ->and($containers['IR-2026-012']->pieces()->count())->toBe(20)
        ->and($containers['IR-2026-013']->goodsCost())->toBe(1906000)
        ->and($containers['IR-2026-013']->totalLandedCost())->toBe(2451000)
        ->and($containers['IR-2026-013']->pieces()->count())->toBe(21)
        ->and($containers['IR-2026-014']->goodsCost())->toBe(2020000)
        ->and($containers['IR-2026-014']->totalLandedCost())->toBe(2660000)
        ->and($containers['IR-2026-014']->items->sum('expected_quantity'))->toBe(28)
        ->and($containers['IR-2026-015']->goodsCost())->toBe(1170000)
        ->and($containers['IR-2026-015']->totalLandedCost())->toBe(1360000)
        ->and($containers['IR-2026-015']->items->sum('expected_quantity'))->toBe(26);

    $pieces = Piece::query()->with('carpet')->orderBy('id')->get();
    $historicalPieceCosts = $pieces
        ->toBase()
        ->groupBy('container_number')
        ->only(['IR-2026-012', 'IR-2026-013'])
        ->map(fn ($containerPieces): array => $containerPieces
            ->groupBy(fn (Piece $piece): string => $piece->carpet->name)
            ->map(fn ($carpetPieces): int => $carpetPieces->first()->landed_cost)
            ->sortKeys()
            ->all())
        ->all();
    expect($pieces)->toHaveCount(41)
        ->and($pieces->first()->code)->toBe('CP-003600')
        ->and($pieces->last()->code)->toBe('CP-003640')
        ->and($pieces->get(0)->grade)->toBe('A')
        ->and($pieces->get(1)->grade)->toBe('A')
        ->and($pieces->get(2)->grade)->toBe('B')
        ->and($historicalPieceCosts['IR-2026-012'])->toBe([
            'Kashan Medallion' => 62500,
            'Nain Fine Weave' => 41667,
            'Tabriz Herati' => 125000,
        ])
        ->and($historicalPieceCosts['IR-2026-013'])->toBe([
            'Bakhtiari Garden' => 79728,
            'Isfahan Shah Abbasi' => 192891,
            'Mashhad Classic' => 167172,
            'Qom Garden' => 90016,
        ]);
});

test('container profitability allocates invoice discounts and excludes voided sales', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $destination = containersTestLocation('Profit Warehouse');
    $container = containersTestRecord($destination, [
        'number' => 'IR-2026-012',
        'status' => 'received',
        'freight_cost' => 210000,
        'customs_duty_cost' => 130000,
        'clearing_cost' => 40000,
        'transport_cost' => 40000,
    ]);
    $tabriz = containersTestCarpet('Tabriz Herati');
    $kashan = containersTestCarpet('Kashan Medallion');
    $nain = containersTestCarpet('Nain Fine Weave');
    containersTestItem($container, $tabriz, 6, 90000);
    containersTestItem($container, $kashan, 8, 45000);
    containersTestItem($container, $nain, 6, 30000);
    $pieces = app(PieceService::class)->receive(
        $tabriz,
        $destination,
        $owner,
        6,
        90000,
        1080000,
        210000,
        130000,
        40000,
        40000,
        $container->number,
        $container->id,
    );
    app(PieceService::class)->receive(
        $kashan,
        $destination,
        $owner,
        8,
        45000,
        1080000,
        210000,
        130000,
        40000,
        40000,
        $container->number,
        $container->id,
    );
    app(PieceService::class)->receive(
        $nain,
        $destination,
        $owner,
        6,
        30000,
        1080000,
        210000,
        130000,
        40000,
        40000,
        $container->number,
        $container->id,
    );
    $firstPiece = $pieces->first();
    $secondPiece = Piece::query()
        ->where('container_id', $container->id)
        ->where('carpet_id', $kashan->id)
        ->firstOrFail();
    $firstPiece->update(['status' => 'sold']);
    $secondPiece->update(['status' => 'sold']);
    $sale = Sale::query()->create([
        'invoice_number' => 'INV-2026-TEST',
        'location_id' => $destination->id,
        'created_by' => $owner->id,
        'status' => 'completed',
        'subtotal' => 277500,
        'discount' => 27750,
        'total' => 249750,
        'sold_at' => now(),
    ]);
    $sale->items()->create([
        'piece_id' => $firstPiece->id,
        'piece_code' => $firstPiece->code,
        'carpet_id' => $tabriz->id,
        'sku' => $tabriz->sku,
        'name' => $firstPiece->displayName(),
        'unit_price' => 185000,
        'unit_cost' => 125000,
    ]);
    $sale->items()->create([
        'piece_id' => $secondPiece->id,
        'piece_code' => $secondPiece->code,
        'carpet_id' => $kashan->id,
        'sku' => $kashan->sku,
        'name' => $secondPiece->displayName(),
        'unit_price' => 92500,
        'unit_cost' => 62500,
    ]);

    $this->actingAs($owner)
        ->get(route('container-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('containers.0.pieces', 20)
            ->where('containers.0.sold', 2)
            ->where('containers.0.sell_through', 10)
            ->where('containers.0.landed_cost', 1500000)
            ->where('containers.0.revenue', 249750)
            ->where('containers.0.profit', 62250)
            ->where('containers.0.margin', 25));

    $sale->update(['status' => 'void']);
    $firstPiece->update(['status' => 'in_stock']);
    $secondPiece->update(['status' => 'in_stock']);

    $this->get(route('container-report.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('containers.0.sold', 0)
            ->where('containers.0.sell_through', 0)
            ->where('containers.0.revenue', 0)
            ->where('containers.0.profit', 0)
            ->where('containers.0.margin', 0));
});

test('container profitability is owner-only and sales users receive 403', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $sales = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($owner)->get(route('container-report.index'))->assertOk();
    $this->actingAs($admin)->get(route('container-report.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('container-report.index'))->assertForbidden();
});

test('container permissions are present only for owner and admin with approved labels', function () {
    expect(config('permissions'))->toContain('containers.manage')
        ->and(config('roles.owner'))->toContain('containers.manage', 'costs.view', 'reports.profit')
        ->and(config('roles.admin'))->toContain('containers.manage')
        ->and(config('roles.admin'))->not->toContain('costs.view', 'reports.profit')
        ->and(config('roles.sales'))->not->toContain('containers.manage');
});

test('demo-seeded containers receive the expected next sequential piece IDs', function () {
    $this->seed(LocationSeeder::class);
    $this->seed(CarpetSeeder::class);
    $this->seed(ContainerSeeder::class);

    $container = Container::query()->where('number', 'IR-2026-014')->firstOrFail();
    $items = $container->items()->get();
    $this->actingAs(User::query()->where('role', UserRole::ADMIN->value)->firstOrFail())
        ->post(route('containers.receive', $container), [
            'quantities' => $items->mapWithKeys(
                fn (ContainerItem $item): array => [$item->id => $item->expected_quantity],
            )->all(),
        ])
        ->assertSessionHas('success_message', '28 pieces received into Kohat Warehouse');

    $receivedPieces = Piece::query()
        ->where('container_id', $container->id)
        ->orderBy('id')
        ->get();
    expect($receivedPieces)->toHaveCount(28)
        ->and($receivedPieces->first()->code)->toBe('CP-003641')
        ->and($receivedPieces->last()->code)->toBe('CP-003668')
        ->and($receivedPieces->every(fn (Piece $piece): bool => $piece->status === 'in_stock'
            && $piece->location_id === $container->destination_id))
        ->toBeTrue()
        ->and(PieceMovement::query()
            ->where('type', 'receive')
            ->where('reference', $container->number)
            ->count())->toBe(28)
        ->and($container->fresh()->status)->toBe('received');
});
