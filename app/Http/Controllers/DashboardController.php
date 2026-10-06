<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\StockInward;
use App\StockOutward;
use App\Supplier;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $products = Product::query();

        $stats = [
            'products' => (clone $products)->count(),
            'categories' => Category::count(),
            'suppliers' => Supplier::count(),
            'stock_value' => (clone $products)->sum(
                DB::raw('current_stock * purchase_price')
            ),
            'low_stock' => (clone $products)
                ->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'out_of_stock' => (clone $products)
                ->where('current_stock', '<=', 0)
                ->count(),
        ];

        $lowStockProducts = Product::with(['category', 'unit'])
            ->where('current_stock', '>', 0)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->orderBy('current_stock')
            ->limit(5)
            ->get();

        $recentInwards = StockInward::with(['product', 'supplier'])
            ->orderBy('inward_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $recentOutwards = StockOutward::with('product')
            ->orderBy('outward_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        $categoryStock = Category::query()
            ->leftJoin('products', 'categories.id', '=', 'products.category_id')
            ->select(
                'categories.id',
                'categories.name',
                DB::raw('COALESCE(SUM(products.current_stock * products.purchase_price), 0) as value_total')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('value_total', 'desc')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'stats',
            'lowStockProducts',
            'recentInwards',
            'recentOutwards',
            'categoryStock'
        ));
    }
}
