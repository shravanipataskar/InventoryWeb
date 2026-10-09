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
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:customers.view')
        ->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->middleware('permission:customers.create')
        ->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:customers.create')
        ->name('customers.store');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->middleware('permission:customers.edit')
        ->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:customers.edit')
        ->name('customers.update');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->name('customers.show');
    Route::patch('/customers/{customer}/status', [CustomerController::class, 'status'])
        ->middleware('permission:customers.edit')
        ->name('customers.status');
    Route::get('/current-stock', [WorkspaceController::class, 'currentStock'])
        ->middleware('permission:stock.view')
        ->name('current-stock.index');
    Route::get('/reports', [WorkspaceController::class, 'reports'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
    Route::get('/reports/download', [WorkspaceController::class, 'downloadReports'])
        ->name('reports.download');
    Route::get('/settings', [WorkspaceController::class, 'settings'])
        ->middleware('permission:settings.view')
        ->name('settings.index');
    Route::get('/general-settings', [WorkspaceController::class, 'generalSettings'])
        ->middleware('permission:settings.view')
        ->name('general-settings.index');
    Route::put('/general-settings', [WorkspaceController::class, 'updateGeneralSettings'])
        ->middleware('permission:settings.edit')
        ->name('general-settings.update');
    Route::get('/locations', [WorkspaceController::class, 'locations'])
        ->middleware('permission:locations.view')
        ->name('locations.index');
    Route::get('/locations/create', [WorkspaceController::class, 'createLocation'])
        ->middleware('permission:locations.create')
        ->name('locations.create');
    Route::post('/locations', [WorkspaceController::class, 'storeLocation'])
        ->middleware('permission:locations.create')
        ->name('locations.store');
    Route::get('/opening-stock', [WorkspaceController::class, 'openingStock'])
        ->middleware('permission:stock.view')
        ->name('opening-stock.index');
    Route::get('/opening-stock/create', [WorkspaceController::class, 'createOpeningStock'])
        ->middleware('permission:stock.create')
        ->name('opening-stock.create');
    Route::post('/opening-stock', [WorkspaceController::class, 'storeOpeningStock'])
        ->middleware('permission:stock.create')
        ->name('opening-stock.store');
    Route::get('/stock-transfers/create', [WorkspaceController::class, 'createStockTransfer'])
        ->middleware('permission:stock.create')
        ->name('stock-transfers.create');
    Route::post('/stock-transfers', [WorkspaceController::class, 'storeStockTransfer'])
        ->middleware('permission:stock.create')
        ->name('stock-transfers.store');
    Route::get('/stock-transfers', [WorkspaceController::class, 'stockTransfers'])
        ->middleware('permission:stock.view')
        ->name('stock-transfers.index');
    Route::get('/stock-transfers/{transfer}', [WorkspaceController::class, 'showStockTransfer'])
        ->where('transfer', '[0-9]+')
        ->name('stock-transfers.show');
    Route::get('/stock-adjustments/create', [WorkspaceController::class, 'createStockAdjustment'])
        ->middleware('permission:stock.create')
        ->name('stock-adjustments.create');
    Route::post('/stock-adjustments', [WorkspaceController::class, 'storeStockAdjustment'])
        ->middleware('permission:stock.create')
        ->name('stock-adjustments.store');
    Route::get('/stock-adjustments', [WorkspaceController::class, 'stockAdjustments'])
        ->middleware('permission:stock.view')
        ->name('stock-adjustments.index');
    Route::get('/stock-movement', [WorkspaceController::class, 'stockMovement'])
        ->middleware('permission:stock.view')
        ->name('stock-movement.index');
    Route::get('/purchase-reports', [WorkspaceController::class, 'purchaseReports'])
        ->middleware('permission:reports.view')
        ->name('purchase-reports.index');
    Route::get('/purchase-reports/export/{format}', [WorkspaceController::class, 'exportPurchaseReports'])
        ->where('format', 'excel|csv|print')
        ->name('purchase-reports.export');
    Route::get('/purchase-reports/{kind}/{id}', [WorkspaceController::class, 'showPurchaseReportRecord'])
        ->where(['kind' => 'receipt|order|inward', 'id' => '[0-9]+'])
        ->name('purchase-reports.show');
    Route::get('/issue-reports', [WorkspaceController::class, 'issueReports'])
        ->middleware('permission:reports.view')
        ->name('issue-reports.index');
    Route::get('/issue-reports/export/{format}', [WorkspaceController::class, 'exportIssueReports'])
        ->where('format', 'excel|csv|print')
        ->name('issue-reports.export');
    Route::get('/issue-reports/{id}', [WorkspaceController::class, 'showIssueReport'])
        ->where('id', '[0-9]+')
        ->name('issue-reports.show');
    Route::get('/stock-valuation', [WorkspaceController::class, 'stockValuation'])
        ->middleware('permission:reports.view')
        ->name('stock-valuation.index');
    Route::get('/stock-valuation/export/{format}', [WorkspaceController::class, 'exportStockValuation'])
        ->where('format', 'excel|csv|print')
        ->name('stock-valuation.export');
    Route::get('/stock-valuation/product/{id}', [WorkspaceController::class, 'showStockValuation'])
        ->where('id', '[0-9]+')
        ->name('stock-valuation.show');
    Route::get('/users', 'UserController@index')
        ->middleware('permission:users.view')
        ->name('users.index');
    Route::get('/users/create', 'UserController@create')
        ->middleware('permission:users.create')
        ->name('users.create');
    Route::post('/users', 'UserController@store')
        ->middleware('permission:users.create')
        ->name('users.store');
    Route::get('/users/{user}', 'UserController@show')
        ->middleware('permission:users.view')
        ->name('users.show');
    Route::get('/users/{user}/edit', 'UserController@edit')
        ->middleware('permission:users.edit')
        ->name('users.edit');
    Route::put('/users/{user}', 'UserController@update')
        ->middleware('permission:users.edit')
        ->name('users.update');
    Route::patch('/users/{user}/status', 'UserController@status')
        ->middleware('permission:users.delete')
        ->name('users.status');
    Route::get('/activity-log', [WorkspaceController::class, 'activityLog'])
        ->middleware('permission:activity_log.view')
        ->name('activity-log.index');
    Route::get('/roles', 'RoleController@index')->middleware('permission:roles.view')->name('roles.index');
    Route::put('/roles/{role}', 'RoleController@update')->middleware('permission:roles.edit')->name('roles.update');
    Route::put('/roles/users/{user}', 'RoleController@assignUser')->middleware('permission:roles.edit')->name('roles.users.update');

    Route::get('/quotations', 'QuotationController@index')->middleware('permission:quotations.view')->name('quotations.index');
    Route::get('/quotations/create', 'QuotationController@create')->middleware('permission:quotations.create')->name('quotations.create');
    Route::post('/quotations', 'QuotationController@store')->middleware('permission:quotations.create')->name('quotations.store');
    Route::get('/quotations/approval', 'QuotationController@approval')->middleware('permission:quotations.approve')->name('quotations.approval');
    Route::post('/quotations/{quotation}/submit', 'QuotationController@submit')->middleware('permission:quotations.edit')->name('quotations.submit');
    Route::post('/quotations/{quotation}/approve', 'QuotationController@approve')->middleware('permission:quotations.approve')->name('quotations.approve');
    Route::post('/quotations/{quotation}/reject', 'QuotationController@reject')->middleware('permission:quotations.reject')->name('quotations.reject');
    Route::post('/quotations/{quotation}/generate-purchase', 'QuotationController@generatePurchase')->middleware('permission:quotations.approve')->name('quotations.generate-purchase');
    Route::get('/quotations/{quotation}', 'QuotationController@show')->middleware('permission:quotations.view')->name('quotations.show');

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
    Route::get('suppliers/{supplier}/documents/{document}', 'SupplierController@document')
        ->name('suppliers.documents.show');
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
    )->middleware('permission:categories.view');

    Route::resource(
        'companies',
        'CompanyController'
    )->except(['destroy'])->middleware('permission:companies.view');
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
    )->middleware('permission:units.view');


    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'suppliers',
        'SupplierController'
    )->middleware('permission:suppliers.view');


    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::resource(
        'products',
        'ProductController'
    )->middleware('permission:products.view');

    Route::resource('purchase-orders', 'PurchaseOrderController')
        ->only(['index', 'create', 'store', 'show'])
        ->middleware('permission:purchase.view');
    Route::get('purchase-orders/{purchaseOrder}/receive', 'GoodsReceiptController@create')
        ->name('goods-receipts.create');
    Route::post('purchase-orders/{purchaseOrder}/receive', 'GoodsReceiptController@store')
        ->middleware('permission:goods_receipts.create')
        ->name('goods-receipts.store');
    Route::get('goods-receipts', 'GoodsReceiptController@index')
        ->middleware('permission:goods_receipts.view')
        ->name('goods-receipts.index');
    Route::get('goods-receipts/{goodsReceipt}', 'GoodsReceiptController@show')
        ->middleware('permission:goods_receipts.view')
        ->name('goods-receipts.show');

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
        'show',
        'create',
        'store',
        'edit',
        'update',
    ])->middleware('permission:stock.view');


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
    ])->middleware('permission:stock.view');

});