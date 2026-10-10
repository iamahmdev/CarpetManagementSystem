<?php

use App\Http\Controllers\CarpetController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReceivablesController;
use App\Http\Controllers\SaleController;
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

    Route::get('carpets', [CarpetController::class, 'index'])
        ->middleware('permission:catalog.view')
        ->name('carpets.index');
    Route::post('carpets', [CarpetController::class, 'store'])
        ->middleware('permission:catalog.manage')
        ->name('carpets.store');

    Route::middleware('permission:sales.create')->group(function () {
        Route::get('new-sale', [SaleController::class, 'create'])->name('new-sale.index');
        Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
        Route::post('sales/{sale}/payments', [PaymentController::class, 'storeForSale'])->name('sales.payments.store');
    });

    Route::middleware('permission:sales.view')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales-history.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('receipts/{receipt}', [PaymentController::class, 'receipt'])->name('receipts.show');
        Route::post('receipts/{receipt}/print', [PaymentController::class, 'printReceipt'])->name('receipts.print');
        Route::post('customers/{customer}/statement-print', [CustomerController::class, 'printStatement'])
            ->name('customers.statement-print');
    });

    Route::middleware('permission:sales.create')->group(function () {
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::post('customers/{customer}/payments', [PaymentController::class, 'storeForCustomer'])
            ->name('customers.payments.store');
    });

    Route::post('receipts/{receipt}/reverse', [PaymentController::class, 'reverse'])
        ->middleware('permission:sales.void')
        ->name('receipts.reverse');
    Route::post('sales/{sale}/void', [SaleController::class, 'void'])
        ->middleware('permission:sales.void')
        ->name('sales.void');

    Route::get('receivables', [ReceivablesController::class, 'index'])
        ->middleware('permission:reports.receivables')
        ->name('receivables.index');

    Route::get('profit', static fn () => Inertia::render('placeholder', [
        'title' => 'Profit',
        'subtitle' => 'Revenue against the landed cost of the pieces sold.',
    ]))
        ->middleware('permission:reports.profit')
        ->name('profit-report.index');

    $placeholderPages = [
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
    ];

    foreach ($placeholderPages as $page) {
        Route::get($page['path'], static fn () => Inertia::render('placeholder', [
            'title' => $page['title'],
            'subtitle' => $page['subtitle'],
        ]))->name($page['name']);
    }
});

require __DIR__.'/settings.php';
