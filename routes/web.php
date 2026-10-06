<?php

use Illuminate\Support\Facades\Route;

Route::get('/', 'DashboardController@index')->name('dashboard');

Route::resource('categories', 'CategoryController');
Route::resource('units', 'UnitController');
Route::resource('suppliers', 'SupplierController');
Route::resource('products', 'ProductController');
Route::resource('stock-inwards', 'StockInwardController')
    ->only(['index', 'create', 'store']);
Route::resource('stock-outwards', 'StockOutwardController')
    ->only(['index', 'create', 'store']);