<?php

use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::OWNER]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated non-owners can visit the dashboard', function () {
    $user = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('unknown routes render the Inertia not found page', function () {
    $this->get('/route-that-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/page')
            ->where('status', 404),
        );
});

test('unimplemented navigation items render their placeholder headers', function () {
    $user = User::factory()->create(['role' => UserRole::SALES]);
    $pages = [
        '/transfers' => 'Transfers',
        '/new-sale' => 'New sale',
        '/sales' => 'Sales history',
        '/customers' => 'Customers',
        '/stock-report' => 'Stock by location',
        '/profit' => 'Profit',
    ];

    foreach ($pages as $path => $title) {
        $response = $this->actingAs($user)->get($path);
        if ($path === '/profit') {
            $response->assertForbidden();
        } elseif ($path === '/new-sale') {
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('sales/create')
                ->has('customers')
                ->has('locations'));
        } elseif ($path === '/sales') {
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('sales/index')
                ->has('sales'));
        } elseif ($path === '/customers') {
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('customers/index')
                ->has('customers'));
        } else {
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('placeholder')
                ->where('title', $title)
                ->has('subtitle'));
        }
    }

    $this->actingAs($user)->get('/containers')->assertForbidden();
    $this->actingAs($user)->get('/container-report')->assertForbidden();
});
