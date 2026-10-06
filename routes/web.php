<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('categories', 'CategoryController');
Route::resource('units', 'UnitController');
Route::resource('suppliers', 'SupplierController');
Route::resource('products', 'ProductController');
Route::resource('stock-inwards', 'StockInwardController')
    ->only(['index', 'create', 'store']);
Route::resource('stock-outwards', 'StockOutwardController')
    ->only(['index', 'create', 'store']);