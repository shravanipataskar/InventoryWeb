<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use App\StockInward;
use App\StockOutward;
use App\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class DashboardController extends Controller
{
    public function index()
    {
        try {
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
                    ->whereColumn('current_stock', '<', 'minimum_stock')
                    ->count(),
                'out_of_stock' => (clone $products)
                    ->where('current_stock', '<=', 0)
                    ->count(),
            ];

            $reorderCount = Product::where('is_active', true)
                ->whereColumn('current_stock', '<', 'minimum_stock')
                ->count();

            $reorderProducts = Product::with('unit')
                ->where('is_active', true)
                ->whereColumn('current_stock', '<', 'minimum_stock')
                ->orderByRaw('CASE WHEN current_stock <= 0 THEN 0 ELSE 1 END')
                ->orderBy('current_stock')
                ->get();

            $recentInwards = StockInward::with(['product', 'supplier'])
                ->where('is_active', 1)
                ->orderBy('inward_date', 'desc')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();

            $recentOutwards = StockOutward::with('product')
                ->where('is_active', 1)
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
                'reorderProducts',
                'reorderCount',
                'recentInwards',
                'recentOutwards',
                'categoryStock'
            ));
        } catch (Throwable $exception) {
            Log::error('Failed to load the inventory dashboard.', [
                'operation' => 'dashboard.index',
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
