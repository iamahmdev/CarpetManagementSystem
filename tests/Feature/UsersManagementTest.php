<?php

use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('owners can view the user list and an individual user', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $user = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($owner)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 2),
        );

    $this->get(route('users.show', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->where('user.id', $user->id)
            ->where('user.role', 'sales'),
        );
});

test('owners can create users with a selected role', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);

    $this->actingAs($owner)
        ->get(route('users.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/create')
            ->where('roles', ['owner', 'admin', 'sales']),
        );

    $response = $this->post(route('users.store'), [
        'name' => 'New Administrator',
        'email' => 'new-admin@example.com',
        'password' => 'strong-password',
        'password_confirmation' => 'strong-password',
        'role' => 'admin',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    $user = User::query()->where('email', 'new-admin@example.com')->firstOrFail();

    expect($user->role)->toBe(UserRole::ADMIN)
        ->and(Hash::check('strong-password', $user->password))->toBeTrue();
});

test('user creation validates fields and role values', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $existingUser = User::factory()->create();

    $this->actingAs($owner)
        ->from(route('users.create'))
        ->post(route('users.store'), [
            'name' => '',
            'email' => $existingUser->email,
            'password' => 'password',
            'password_confirmation' => 'different-password',
            'role' => 'manager',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password', 'role']);
});

test('owners can update user information and change a user role', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $user = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($owner)
        ->get(route('users.edit', $user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/edit')
            ->where('user.role', 'sales'),
        );

    $this->patch(route('users.update', $user), [
        'name' => 'Updated User',
        'email' => 'updated-user@example.com',
        'role' => 'admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertSessionHasNoErrors()->assertRedirect(route('users.index'));

    expect($user->refresh()->name)->toBe('Updated User')
        ->and($user->email)->toBe('updated-user@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->role)->toBe(UserRole::ADMIN);
});

test('owners can delete another user but cannot delete themselves from user management', function () {
    $owner = User::factory()->create(['role' => UserRole::OWNER]);
    $user = User::factory()->create();

    $this->actingAs($owner)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    expect($user->fresh())->toBeNull();

    $this->delete(route('users.destroy', $owner))
        ->assertForbidden();

    expect($owner->fresh())->not->toBeNull();
});

test('admins can manage users only within their configured permissions', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $user = User::factory()->create(['role' => UserRole::SALES]);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk();

    $this->post(route('users.store'), [
        'name' => 'Sales User',
        'email' => 'sales-user@example.com',
        'password' => 'strong-password',
        'password_confirmation' => 'strong-password',
        'role' => 'sales',
    ])->assertRedirect(route('users.index'));

    $this->patch(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role' => 'admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('users.index'));

    $this->delete(route('users.destroy', $user))
        ->assertForbidden();

    expect($user->refresh()->role)->toBe(UserRole::ADMIN);
});

test('each configured permission is granted to the correct roles', function () {
    expect(config('permissions'))->toBe([
        'users.manage',
        'locations.manage',
        'catalog.view',
        'catalog.manage',
        'stock.view',
        'costs.view',
        'sales.create',
        'sales.view',
        'sales.void',
        'reports.profit',
        'reports.receivables',
    ])
        ->and(config('roles.owner'))->toBe([
            'users.manage',
            'locations.manage',
            'catalog.view',
            'catalog.manage',
            'stock.view',
            'costs.view',
            'sales.create',
            'sales.view',
            'sales.void',
            'reports.profit',
            'reports.receivables',
        ])
        ->and(config('roles.admin'))->toBe([
            'users.manage',
            'catalog.view',
            'catalog.manage',
            'stock.view',
            'sales.create',
            'sales.view',
            'sales.void',
            'reports.receivables',
        ])
        ->and(config('roles.sales'))->toBe(['catalog.view', 'stock.view', 'sales.create', 'sales.view']);
});

test('sales users cannot access user management', function () {
    $salesUser = User::factory()->create(['role' => UserRole::SALES]);
    $otherUser = User::factory()->create();

    $this->actingAs($salesUser)
        ->get(route('users.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/page')
            ->where('status', 403)
            ->where('role', 'Sales')
            ->where('pageLabel', 'Users and Roles'),
        );

    $this->get(route('users.create'))->assertForbidden();
    $this->post(route('users.store'), [])->assertForbidden();
    $this->get(route('users.show', $otherUser))->assertForbidden();
    $this->get(route('users.edit', $otherUser))->assertForbidden();
    $this->patch(route('users.update', $otherUser), [])->assertForbidden();
    $this->delete(route('users.destroy', $otherUser))->assertForbidden();
});
