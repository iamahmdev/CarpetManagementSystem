<?php

use App\Http\Controllers\LocationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    Route::middleware('permission:locations.manage')->group(function () {
        Route::get('locations', [LocationController::class, 'index'])
            ->name('locations.index');
        Route::post('locations', [LocationController::class, 'store'])
            ->name('locations.store');
    });

    $placeholderPages = [
        [
            'path' => 'carpets',
            'name' => 'carpets.index',
            'title' => 'Carpets',
            'subtitle' => 'The catalog of varieties. Each physical carpet is tracked separately under Pieces.',
        ],
        [
            'path' => 'pieces',
            'name' => 'pieces.index',
            'title' => 'Pieces',
            'subtitle' => 'Every physical carpet with its own ID. Search by code, location, or status.',
        ],
        [
            'path' => 'containers',
            'name' => 'containers.index',
            'title' => 'Containers',
            'subtitle' => 'Imports from Iran. Receiving a container creates one record per carpet, each with its own ID and label.',
        ],
        [
            'path' => 'transfers',
            'name' => 'transfers.index',
            'title' => 'Transfers',
            'subtitle' => 'Move pieces from a warehouse to its display. Stock only changes location when the other side confirms.',
        ],
        [
            'path' => 'new-sale',
            'name' => 'new-sale.index',
            'title' => 'New sale',
            'subtitle' => 'Pick the display, add pieces by ID, take payment, and print the invoice.',
        ],
        [
            'path' => 'sales',
            'name' => 'sales-history.index',
            'title' => 'Sales history',
            'subtitle' => 'All invoices across the displays.',
        ],
        [
            'path' => 'customers',
            'name' => 'customers.index',
            'title' => 'Customers',
            'subtitle' => 'Buyers, their purchases, and any balance still due.',
        ],
        [
            'path' => 'stock-report',
            'name' => 'stock-report.index',
            'title' => 'Stock by location',
            'subtitle' => 'What is on hand right now, at each warehouse and display.',
        ],
        [
            'path' => 'container-report',
            'name' => 'container-report.index',
            'title' => 'Container profitability',
            'subtitle' => 'Which imports are paying back, based on real sales and landed cost.',
        ],
        [
            'path' => 'profit',
            'name' => 'profit-report.index',
            'title' => 'Profit',
            'subtitle' => 'Revenue against the landed cost of the pieces sold.',
        ],
    ];

    foreach ($placeholderPages as $page) {
        Route::get($page['path'], static fn () => Inertia::render('placeholder', [
            'title' => $page['title'],
            'subtitle' => $page['subtitle'],
        ]))->name($page['name']);
    }
});

require __DIR__.'/settings.php';
