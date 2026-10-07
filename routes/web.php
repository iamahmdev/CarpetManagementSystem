<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('users', [UserController::class, 'index'])
        ->middleware('permission:users.manage')
        ->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])
        ->middleware('permission:users.manage')
        ->name('users.create');
    Route::post('users', [UserController::class, 'store'])
        ->middleware('permission:users.manage')
        ->name('users.store');
    Route::get('users/{user}', [UserController::class, 'show'])
        ->middleware('permission:users.manage')
        ->name('users.show');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])
        ->middleware('permission:users.manage')
        ->name('users.edit');
    Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])
        ->middleware(['permission:users.manage', 'role:owner'])
        ->name('users.destroy');
});

require __DIR__.'/settings.php';
