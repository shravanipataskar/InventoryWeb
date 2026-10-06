<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public / Authentication Routes
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Landing Page
|--------------------------------------------------------------------------
*/

Route::get('/', [AuthController::class, 'showIndex'])
    ->name('landing');


/*
|--------------------------------------------------------------------------
| Login
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.submit');


/*
|--------------------------------------------------------------------------
| OTP Verification
|--------------------------------------------------------------------------
*/

Route::get('/login/verify', [AuthController::class, 'showVerifyOtp'])
    ->name('login.otp.show');

Route::post('/login/verify', [AuthController::class, 'verifyOtp'])
    ->name('login.otp.verify');

Route::post('/login/verify/resend', [AuthController::class, 'resendOtp'])
    ->name('login.otp.resend');


/*
|--------------------------------------------------------------------------
| Register
|--------------------------------------------------------------------------
*/

Route::get('/register', [AuthController::class, 'showRegister'])
    ->name('register');

Route::post('/register', [AuthController::class, 'register'])
    ->name('register.submit');


/*
|--------------------------------------------------------------------------
| Forgot Password
|--------------------------------------------------------------------------
*/

Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])
    ->name('password.request');

Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])
    ->name('password.email');


/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/

Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])
    ->name('password.reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->name('password.update');


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthController::class, 'logout'])
    ->name('logout');


/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
|
| Everything inside this group requires the user to be authenticated.
|
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [AuthController::class, 'showProfile'])
        ->name('profile.show');

    Route::put('/profile', [AuthController::class, 'updateProfile'])
        ->name('profile.update');


    /*
    |--------------------------------------------------------------------------
    | Change Password
    |--------------------------------------------------------------------------
    */

    Route::get('/change-password', [AuthController::class, 'showChangePassword'])
        ->name('password.change');

    Route::put('/change-password', [AuthController::class, 'updatePassword'])
        ->name('password.change.update');


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', 'DashboardController@index')
        ->name('dashboard');

    Route::patch('categories/{category}/status', 'CategoryController@status')
        ->name('categories.status');
    Route::patch('units/{unit}/status', 'UnitController@status')
        ->name('units.status');
    Route::patch('suppliers/{supplier}/status', 'SupplierController@status')
        ->name('suppliers.status');
    Route::patch('products/{product}/status', 'ProductController@status')
        ->name('products.status');
    Route::patch('stock-inwards/{stockInward}/status', 'StockInwardController@status')
        ->name('stock-inwards.status');
    Route::patch('stock-outwards/{stockOutward}/status', 'StockOutwardController@status')
        ->name('stock-outwards.status');

    /*
    |--------------------------------------------------------------------------
    | Categories
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'categories',
        'CategoryController'
    );


    /*
    |--------------------------------------------------------------------------
    | Units
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'units',
        'UnitController'
    );


    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'suppliers',
        'SupplierController'
    );


    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'products',
        'ProductController'
    );


    /*
    |--------------------------------------------------------------------------
    | Stock Inward
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'stock-inwards',
        'StockInwardController'
    )->only([
        'index',
        'create',
        'store',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Stock Outward
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'stock-outwards',
        'StockOutwardController'
    )->only([
        'index',
        'create',
        'store',
    ]);

});