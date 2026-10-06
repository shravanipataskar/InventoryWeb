<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'showIndex'])->name('landing');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');

Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');

Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', 'DashboardController@index')->name('dashboard');

    Route::resource('categories', 'CategoryController');
    Route::resource('units', 'UnitController');
    Route::resource('suppliers', 'SupplierController');
    Route::resource('products', 'ProductController');
    Route::resource('stock-inwards', 'StockInwardController')
        ->only(['index', 'create', 'store']);
    Route::resource('stock-outwards', 'StockOutwardController')
        ->only(['index', 'create', 'store']);
});
