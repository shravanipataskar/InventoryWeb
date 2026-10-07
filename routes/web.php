<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WorkspaceController;
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

    Route::get('/customers', [CustomerController::class, 'index'])
        ->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->name('customers.store');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->name('customers.update');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->name('customers.show');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'status'])
        ->name('customers.status');
    Route::get('/current-stock', [WorkspaceController::class, 'currentStock'])
        ->name('current-stock.index');
    Route::get('/reports', [WorkspaceController::class, 'reports'])
        ->name('reports.index');
    Route::get('/reports/download', [WorkspaceController::class, 'downloadReports'])
        ->name('reports.download');
    Route::get('/settings', [WorkspaceController::class, 'settings'])
        ->name('settings.index');
    Route::get('/general-settings', [WorkspaceController::class, 'generalSettings'])
        ->name('general-settings.index');
    Route::put('/general-settings', [WorkspaceController::class, 'updateGeneralSettings'])
        ->name('general-settings.update');
    Route::get('/locations', [WorkspaceController::class, 'locations'])
        ->name('locations.index');
    Route::get('/locations/create', [WorkspaceController::class, 'createLocation'])
        ->name('locations.create');
    Route::post('/locations', [WorkspaceController::class, 'storeLocation'])
        ->name('locations.store');
    Route::get('/opening-stock', [WorkspaceController::class, 'openingStock'])
        ->name('opening-stock.index');
    Route::get('/opening-stock/create', [WorkspaceController::class, 'createOpeningStock'])
        ->name('opening-stock.create');
    Route::post('/opening-stock', [WorkspaceController::class, 'storeOpeningStock'])
        ->name('opening-stock.store');
    Route::get('/stock-transfers/create', [WorkspaceController::class, 'createStockTransfer'])
        ->name('stock-transfers.create');
    Route::post('/stock-transfers', [WorkspaceController::class, 'storeStockTransfer'])
        ->name('stock-transfers.store');
    Route::get('/stock-transfers', [WorkspaceController::class, 'stockTransfers'])
        ->name('stock-transfers.index');
    Route::get('/stock-adjustments/create', [WorkspaceController::class, 'createStockAdjustment'])
        ->name('stock-adjustments.create');
    Route::post('/stock-adjustments', [WorkspaceController::class, 'storeStockAdjustment'])
        ->name('stock-adjustments.store');
    Route::get('/stock-adjustments', [WorkspaceController::class, 'stockAdjustments'])
        ->name('stock-adjustments.index');
    Route::get('/stock-movement', [WorkspaceController::class, 'stockMovement'])
        ->name('stock-movement.index');
    Route::get('/purchase-reports', [WorkspaceController::class, 'purchaseReports'])
        ->name('purchase-reports.index');
    Route::get('/issue-reports', [WorkspaceController::class, 'issueReports'])
        ->name('issue-reports.index');
    Route::get('/stock-valuation', [WorkspaceController::class, 'stockValuation'])
        ->name('stock-valuation.index');
    Route::get('/users', [WorkspaceController::class, 'users'])
        ->name('users.index');
    Route::get('/activity-log', [WorkspaceController::class, 'activityLog'])
        ->name('activity-log.index');

    Route::patch('categories/{category}/status', 'CategoryController@status')
        ->name('categories.status');
    Route::patch('companies/{company}/status', 'CompanyController@status')
        ->name('companies.status');
    Route::post('companies/{company}/documents', 'CompanyController@storeDocument')
        ->name('companies.documents.store');
    Route::get('companies/{company}/documents/{document}', 'CompanyController@downloadDocument')
        ->name('companies.documents.download');
    Route::delete('companies/{company}/documents/{document}', 'CompanyController@destroyDocument')
        ->name('companies.documents.destroy');
    Route::patch('units/{unit}/status', 'UnitController@status')
        ->name('units.status');
    Route::patch('suppliers/{supplier}/status', 'SupplierController@status')
        ->name('suppliers.status');
    Route::patch('products/{product}/status', 'ProductController@status')
        ->name('products.status');
    Route::patch('halls/{hall}/status', 'HallController@status')
        ->name('halls.status');
    Route::patch('racks/{rack}/status', 'RackController@status')
        ->name('racks.status');
    Route::patch('shelves/{shelf}/status', 'ShelfController@status')
        ->name('shelves.status');
    Route::get('locations/halls/{hall}/racks', 'HallController@racksOptions')
        ->name('locations.halls.racks');
    Route::get('locations/racks/{rack}/shelves', 'RackController@shelvesOptions')
        ->name('locations.racks.shelves');

    Route::get('halls/{hall}/racks/create', 'RackController@create')
        ->name('racks.create');
    Route::post('halls/{hall}/racks', 'RackController@store')
        ->name('racks.store');
    Route::get('racks/{rack}', 'RackController@show')
        ->name('racks.show');
    Route::get('racks/{rack}/edit', 'RackController@edit')
        ->name('racks.edit');
    Route::put('racks/{rack}', 'RackController@update')
        ->name('racks.update');

    Route::get('racks/{rack}/shelves/create', 'ShelfController@create')
        ->name('shelves.create');
    Route::post('racks/{rack}/shelves', 'ShelfController@store')
        ->name('shelves.store');
    Route::get('shelves/{shelf}/edit', 'ShelfController@edit')
        ->name('shelves.edit');
    Route::put('shelves/{shelf}', 'ShelfController@update')
        ->name('shelves.update');

    Route::resource('halls', 'HallController')->except(['destroy']);
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

    Route::resource(
        'companies',
        'CompanyController'
    )->except(['destroy']);
    Route::delete('companies/{company}', 'CompanyController@destroy')
        ->name('companies.destroy');


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