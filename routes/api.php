<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CustomerController;
use App\Http\Controllers\API\InvoiceController;
use App\Http\Controllers\API\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);
});

Route::middleware(['api'])->group(function () {
    Route::prefix('customers')->controller(CustomerController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('store', 'store');
        Route::get('details/{customer}', 'details');
        Route::put('update/{customer}', 'update');
        Route::delete('delete/{customer}', 'delete');
    });

    Route::prefix('products')->controller(ProductController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('store', 'store');
        Route::get('details/{product}', 'details');
        Route::put('update/{product}', 'update');
        Route::delete('delete/{product}', 'delete');
    });

    Route::prefix('invoice')->controller(InvoiceController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('store', 'store');
        Route::get('details/{invoice_number}', 'details');
        Route::put('update/{invoice_number}', 'update');
        Route::put('change/status/{invoice_number}', 'changeStatus');
        Route::delete('delete/{invoice_number}', 'delete');
    });
});
