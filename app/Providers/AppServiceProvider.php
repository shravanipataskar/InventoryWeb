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
use Illuminate\Support\Facades\View;

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
        View::composer('layouts.app', function ($view) {
            if (!auth()->check()) {
                return;
            }

            $reorderQuery = Product::where('is_active', true)
                ->whereColumn('current_stock', '<', 'minimum_stock');

            $view->with([
                'headerReorderCount' => (clone $reorderQuery)->count(),
                'headerReorderProducts' => (clone $reorderQuery)
                    ->with('unit')
                    ->orderByRaw('CASE WHEN current_stock <= 0 THEN 0 ELSE 1 END')
                    ->orderBy('current_stock')
                    ->limit(2)
                    ->get(),
            ]);
        });

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
