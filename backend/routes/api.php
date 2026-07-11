<?php

use App\Http\Controllers\Api\PosApiController;
use Illuminate\Support\Facades\Route;

// Public authentication entry point.
Route::post('/login', [PosApiController::class, 'login']);

Route::middleware('pos.auth')->group(function () {
    // Session/bootstrap endpoints.
    Route::get('/me', [PosApiController::class, 'me']);
    Route::post('/change-password', [PosApiController::class, 'changePassword']);

    // Catalog management.
    Route::get('/products', [PosApiController::class, 'products']);
    Route::post('/products', [PosApiController::class, 'createProduct']);
    Route::match(['put', 'patch'], '/products/{id}', [PosApiController::class, 'updateProduct']);
    Route::delete('/products/{id}', [PosApiController::class, 'deleteProduct']);

    Route::get('/categories', [PosApiController::class, 'categories']);
    Route::post('/categories', [PosApiController::class, 'createCategory']);
    Route::delete('/categories/{name}', [PosApiController::class, 'deleteCategory'])->where('name', '.*');

    // User administration.
    Route::get('/users', [PosApiController::class, 'users']);
    Route::post('/users', [PosApiController::class, 'createUser']);
    Route::match(['put', 'patch'], '/users/{id}', [PosApiController::class, 'updateUser']);
    Route::patch('/users/{id}/status', [PosApiController::class, 'updateUserStatus']);
    Route::delete('/users/{id}', [PosApiController::class, 'deleteUser']);

    // POS and inventory operations.
    Route::get('/transactions', [PosApiController::class, 'transactions']);
    Route::post('/transactions', [PosApiController::class, 'createTransaction']);
    Route::post('/transactions/{id}/void', [PosApiController::class, 'voidTransaction']);

    Route::get('/stock-movements', [PosApiController::class, 'stockMovements']);
    Route::post('/stock-movements', [PosApiController::class, 'createStockMovement']);

    // Store configuration and back-office records.
    Route::get('/settings', [PosApiController::class, 'settings']);
    Route::match(['put', 'patch'], '/settings', [PosApiController::class, 'updateSettings']);

    Route::get('/expenses', [PosApiController::class, 'expenses']);
    Route::post('/expenses', [PosApiController::class, 'createExpense']);

    Route::get('/audit-logs', [PosApiController::class, 'auditLogs']);
    Route::post('/audit-logs', [PosApiController::class, 'addAuditLog']);

    // Order workflow and credit tracking.
    Route::get('/orders', [PosApiController::class, 'orders']);
    Route::post('/orders', [PosApiController::class, 'createOrder']);
    Route::match(['put', 'patch'], '/orders/{id}', [PosApiController::class, 'updateOrder']);
    Route::post('/orders/{id}/lock', [PosApiController::class, 'acquireOrderEditLock']);
    Route::delete('/orders/{id}/lock', [PosApiController::class, 'releaseOrderEditLock']);

    Route::get('/credits', [PosApiController::class, 'credits']);
    Route::post('/credits', [PosApiController::class, 'createCredit']);
    Route::post('/credits/{id}/payments', [PosApiController::class, 'addCreditPayment']);
    Route::patch('/credits/{id}/due-date', [PosApiController::class, 'updateCreditDueDate']);

    // One-off maintenance action for older transaction data.
    Route::post('/migrate-or-numbers', [PosApiController::class, 'migrateOrNumbers']);
});
