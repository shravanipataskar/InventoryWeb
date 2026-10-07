<?php

namespace App\Providers;

use App\Category;
use App\Company;
use App\Observers\ActivityLogObserver;
use App\Product;
use App\StockAdjustment;
use App\StockInward;
use App\StockOutward;
use App\StockTransfer;
use App\Supplier;
use App\Unit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        foreach ([
            Category::class,
            Company::class,
            Product::class,
            StockAdjustment::class,
            StockInward::class,
            StockOutward::class,
            StockTransfer::class,
            Supplier::class,
            Unit::class,
        ] as $model) {
            $model::observe(ActivityLogObserver::class);
        }
    }
}
