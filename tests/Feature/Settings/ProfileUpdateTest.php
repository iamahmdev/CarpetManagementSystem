<?php

use App\Models\User;
use App\UserRole;
use Inertia\Testing\AssertableInertia;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile navigation URL opens the authenticated user profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/profile'),
        );
});

test('profile navigation does not require a profile permission', function () {
    $user = User::factory()->create();
    config(['roles.sales' => []]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk();
});

test('owners can view and update their own profile', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->patch(route('profile.update'), [
        'name' => 'Owner Profile',
        'email' => $owner->email,
    ])->assertRedirect(route('profile.edit'));

    expect($owner->refresh()->name)->toBe('Owner Profile');
});

test('profile updates only affect the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'user_id' => $otherUser->id,
            'name' => 'Updated Own Profile',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Updated Own Profile')
        ->and($otherUser->refresh()->name)->not->toBe('Updated Own Profile');
});

test('profile update requires the existing profile update permission', function () {
    $user = User::factory()->create();
    config(['roles.sales' => ['profile.view']]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk();

    $this->patch(route('profile.update'), [
        'name' => 'Unauthorized Update',
        'email' => $user->email,
    ])->assertForbidden();

    expect($user->refresh()->name)->not->toBe('Unauthorized Update');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('users cannot delete their own account from the profile settings', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'password',
        ]);

    $response->assertMethodNotAllowed();
    expect($user->fresh())->not->toBeNull();
});

test('invalid password cannot bypass disabled profile deletion', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/settings/profile', [
            'password' => 'wrong-password',
        ]);

    $response->assertMethodNotAllowed();
    expect($user->fresh())->not->toBeNull();
});
