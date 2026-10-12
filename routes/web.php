<?php

use App\Http\Controllers\CarpetController;
use App\Http\Controllers\ContainerController;
use App\Http\Controllers\ContainerReportController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PieceController;
use App\Http\Controllers\ReceivablesController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StockReportController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::inertia('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
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

    Route::get('pieces', [PieceController::class, 'index'])
        ->middleware('permission:stock.view')
        ->name('pieces.index');
    Route::get('pieces/search', [PieceController::class, 'search'])
        ->name('pieces.search');

    Route::middleware('permission:containers.manage')->group(function () {
        Route::get('containers', [ContainerController::class, 'index'])
            ->name('containers.index');
        Route::post('containers/{container}/arrive', [ContainerController::class, 'markArrived'])
            ->name('containers.arrive');
        Route::post('containers/{container}/receive', [ContainerController::class, 'receive'])
            ->name('containers.receive');
    });

    Route::middleware('permission:sales.create')->group(function () {
        Route::get('pieces/lookup', [SaleController::class, 'lookupPiece'])->name('pieces.lookup');
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

    Route::get('container-report', [ContainerReportController::class, 'index'])
        ->middleware('permission:reports.profit')
        ->name('container-report.index');

    Route::middleware('permission:transfers.manage')->group(function () {
        Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/pieces', [TransferController::class, 'pieces'])->name('transfers.pieces');
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
    });

    Route::get('stock-report', [StockReportController::class, 'index'])
        ->middleware('permission:reports.stock')
        ->name('stock-report.index');
});

require __DIR__.'/settings.php';
