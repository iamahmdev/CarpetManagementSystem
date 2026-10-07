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
