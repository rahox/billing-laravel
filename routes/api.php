<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Master data pelanggan: semua role terkait bisa lihat, create dibatasi role tertentu, hapus khusus owner.
    Route::get('customers', [CustomerController::class, 'index']);
    Route::post('customers', [CustomerController::class, 'store'])->middleware('role:super-admin,reseller,sales');
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::patch('customers/{customer}', [CustomerController::class, 'update'])->middleware('role:super-admin,reseller');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('role:super-admin');

    // Produk & diskon: dikelola owner, dilihat semua role.
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::middleware('role:super-admin')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::patch('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
    });

    Route::get('discounts', [DiscountController::class, 'index']);
    Route::get('discounts/{discount}', [DiscountController::class, 'show']);
    Route::middleware('role:super-admin')->group(function () {
        Route::post('discounts', [DiscountController::class, 'store']);
        Route::patch('discounts/{discount}', [DiscountController::class, 'update']);
        Route::delete('discounts/{discount}', [DiscountController::class, 'destroy']);
    });

    // Transaksi (item invoice): owner & reseller yang membuat.
    Route::get('transactions', [TransactionController::class, 'index']);
    Route::post('transactions', [TransactionController::class, 'store'])->middleware('role:super-admin,reseller');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show']);
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->middleware('role:super-admin,reseller');

    // Invoice: dibentuk dari transaksi oleh owner & reseller.
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices', [InvoiceController::class, 'store'])->middleware('role:super-admin,reseller');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);

    // Pembayaran: dicatat & dikonfirmasi oleh collector (atau owner).
    Route::get('payments', [PaymentController::class, 'index']);
    Route::post('payments', [PaymentController::class, 'store'])->middleware('role:super-admin,collector');
    Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm'])->middleware('role:super-admin,collector');

    // Beban/pembelian: dicatat owner.
    Route::get('expenses', [ExpenseController::class, 'index'])->middleware('role:super-admin');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('role:super-admin');

    // Daftar user (dropdown sales/collector dsb): dapat diakses semua role, hasil dibatasi di controller.
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{user}', [UserController::class, 'show']);

    // Manajemen (create/update) user reseller/sales/collector: khusus owner.
    Route::middleware('role:super-admin')->group(function () {
        Route::post('users', [UserController::class, 'store']);
        Route::patch('users/{user}', [UserController::class, 'update']);
    });

    Route::prefix('reports')->group(function () {
        Route::get('transactions', [ReportController::class, 'transactions'])->middleware('role:super-admin,reseller');
        Route::get('sales', [ReportController::class, 'sales'])->middleware('role:super-admin,sales');
        Route::get('collector', [ReportController::class, 'collector'])->middleware('role:super-admin,collector');
        Route::get('income-statement', [ReportController::class, 'incomeStatement'])->middleware('role:super-admin');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('role:super-admin');
    });
});
