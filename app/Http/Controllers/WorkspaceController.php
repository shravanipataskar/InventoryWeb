<?php

namespace App\Http\Controllers;

use App\Category;
use App\Company;
use App\Product;
use App\StockAdjustment;
use App\StockInward;
use App\StockOutward;
use App\StockTransfer;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    public function customers(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        if (Schema::hasTable('customers')) {
            $customerQuery = DB::table('customers')
                ->leftJoin('stock_outwards', function ($join) {
                    $join->on('stock_outwards.issued_to', '=', 'customers.name');
                    if (Schema::hasColumn('stock_outwards', 'is_active')) {
                        $join->where('stock_outwards.is_active', true);
                    }
                });
            if (Schema::hasColumn('customers', 'is_active')) {
                $customerQuery->where('customers.is_active', true);
            }
            $customerCountQuery = DB::table('customers');
            if (Schema::hasColumn('customers', 'is_active')) {
                $customerCountQuery->where('is_active', true);
            }
            $customerCount = $customerCountQuery->count();

            if ($search !== '') {
                $customerQuery->where('customers.name', 'like', '%' . $search . '%');
            }

            $customers = $customerQuery
                ->select(
                    'customers.name as issued_to',
                    DB::raw('COUNT(stock_outwards.id) as orders_count'),
                    DB::raw('COALESCE(SUM(stock_outwards.quantity), 0) as quantity_issued'),
                    DB::raw('COALESCE(SUM(stock_outwards.total_amount), 0) as total_value'),
                    DB::raw('MAX(stock_outwards.outward_date) as last_order_date')
                )
                ->groupBy('customers.id', 'customers.name')
                ->orderBy('customers.name')
                ->paginate(15)
                ->appends($request->query());
        } else {
            $customerQuery = DB::table('stock_outwards')
                ->where('stock_outwards.is_active', true)
                ->whereNotNull('issued_to')
                ->where('issued_to', '<>', '');

            $customerCount = (clone $customerQuery)->distinct()->count('issued_to');

            if ($search !== '') {
                $customerQuery->where('issued_to', 'like', '%' . $search . '%');
            }

            $customers = $customerQuery
                ->select(
                    'issued_to',
                    DB::raw('COUNT(*) as orders_count'),
                    DB::raw('COALESCE(SUM(quantity), 0) as quantity_issued'),
                    DB::raw('COALESCE(SUM(total_amount), 0) as total_value'),
                    DB::raw('MAX(outward_date) as last_order_date')
                )
                ->groupBy('issued_to')
                ->orderBy('issued_to')
                ->paginate(15)
                ->appends($request->query());
        }

        $customerSummary = DB::table('stock_outwards')
            ->where('is_active', true)
            ->whereNotNull('issued_to')
            ->where('issued_to', '<>', '')
            ->selectRaw('COUNT(DISTINCT issued_to) as customers_count, COALESCE(SUM(total_amount), 0) as sales_total')
            ->first();

        return view('workspace.customers', compact(
            'customers',
            'customerCount',
            'customerSummary',
            'search'
        ));
    }

    public function currentStock(Request $request)
    {
        $query = Product::with([
            'category',
            'company',
            'unit',
            'hallLocation',
            'rackLocation',
            'shelfLocation',
        ])
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($productQuery) use ($search) {
                $productQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('product_code', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            });
        }

        if ($request->input('stock') === 'low') {
            $query->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock');
        } elseif ($request->input('stock') === 'out') {
            $query->where('current_stock', '<=', 0);
        } elseif ($request->input('stock') === 'available') {
            $query->where('current_stock', '>', 0)
                ->where(function ($productQuery) {
                    $productQuery->whereColumn('current_stock', '>', 'minimum_stock')
                        ->orWhere('minimum_stock', '<=', 0);
                });
        }

        $products = $query->orderBy('name')
            ->paginate(20)
            ->appends($request->query());

        $summaryQuery = Product::where('is_active', true);
        $summary = [
            'products' => (clone $summaryQuery)->count(),
            'quantity' => (clone $summaryQuery)->sum('current_stock'),
            'value' => (clone $summaryQuery)->sum(DB::raw('current_stock * purchase_price')),
            'low_stock' => (clone $summaryQuery)
                ->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'out_of_stock' => (clone $summaryQuery)->where('current_stock', '<=', 0)->count(),
        ];

        return view('workspace.current-stock', compact('products', 'summary'));
    }

    public function reports()
    {
        return view('workspace.reports', $this->reportData());
    }

    public function downloadReports()
    {
        $report = $this->reportData();
        $business = DB::table('system_settings')
            ->whereIn('key', ['business_name', 'business_email', 'business_phone', 'business_address'])
            ->pluck('value', 'key');

        return response()->streamDownload(function () use ($report, $business) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            $writeRow = function (array $row) use ($output) {
                $row = array_map(function ($value) {
                    if (is_string($value) && preg_match('/^\s*[=+\-@]/', $value)) {
                        return "'" . $value;
                    }

                    return $value;
                }, $row);

                fputcsv($output, $row);
            };

            $writeRow([$business->get('business_name', 'Aayojan Ai Inventory System')]);
            if ($business->get('business_email')) {
                $writeRow(['Email', $business->get('business_email')]);
            }
            if ($business->get('business_phone')) {
                $writeRow(['Phone', $business->get('business_phone')]);
            }
            if ($business->get('business_address')) {
                $writeRow(['Address', $business->get('business_address')]);
            }
            $writeRow(['Inventory Report']);
            $writeRow(['Generated at', now()->format('Y-m-d H:i:s')]);
            $writeRow([]);
            $writeRow(['Summary', 'Value']);
            $writeRow(['Stock received quantity', $report['movement']['inward_quantity']]);
            $writeRow(['Stock received purchase value', $report['movement']['inward_value']]);
            $writeRow(['Stock issued quantity', $report['movement']['outward_quantity']]);
            $writeRow(['Stock issued sale value', $report['movement']['outward_value']]);
            $writeRow(['On-hand quantity', $report['stock']['quantity']]);
            $writeRow(['Current stock value', $report['stock']['value']]);
            $writeRow(['Low stock products', $report['stock']['low_stock']]);
            $writeRow(['Out of stock products', $report['stock']['out_of_stock']]);
            $writeRow([]);
            $writeRow(['Stock Value by Category']);
            $writeRow(['Category', 'Products', 'Quantity', 'Stock Value']);
            foreach ($report['categoryReport'] as $category) {
                $writeRow([
                    $category->name,
                    $category->products_count,
                    $category->quantity_total,
                    $category->value_total,
                ]);
            }
            $writeRow([]);
            $writeRow(['Stock Value by Company']);
            $writeRow(['Company', 'Code', 'Products', 'Stock Value']);
            foreach ($report['companyReport'] as $company) {
                $writeRow([
                    $company->name,
                    $company->code,
                    $company->products_count,
                    $company->value_total,
                ]);
            }

            fclose($output);
        }, 'inventory-report-' . now()->format('Y-m-d-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function reportData()
    {
        $products = Product::where('is_active', true);
        $stock = [
            'quantity' => (clone $products)->sum('current_stock'),
            'value' => (clone $products)->sum(DB::raw('current_stock * purchase_price')),
            'low_stock' => (clone $products)
                ->where('current_stock', '>', 0)
                ->whereColumn('current_stock', '<=', 'minimum_stock')
                ->count(),
            'out_of_stock' => (clone $products)->where('current_stock', '<=', 0)->count(),
        ];

        $categoryReport = Category::leftJoin('products', function ($join) {
            $join->on('categories.id', '=', 'products.category_id')
                ->where('products.is_active', true);
        })->select(
            'categories.id',
            'categories.name',
            DB::raw('COUNT(products.id) as products_count'),
            DB::raw('COALESCE(SUM(products.current_stock), 0) as quantity_total'),
            DB::raw('COALESCE(SUM(products.current_stock * products.purchase_price), 0) as value_total')
        )->groupBy('categories.id', 'categories.name')
            ->orderBy('value_total', 'desc')
            ->get();

        $companyReport = Company::leftJoin('products', function ($join) {
            $join->on('companies.id', '=', 'products.company_id')
                ->where('products.is_active', true);
        })->select(
            'companies.id',
            'companies.name',
            'companies.code',
            DB::raw('COUNT(products.id) as products_count'),
            DB::raw('COALESCE(SUM(products.current_stock * products.purchase_price), 0) as value_total')
        )->groupBy('companies.id', 'companies.name', 'companies.code')
            ->orderBy('value_total', 'desc')
            ->get();

        $movement = [
            'inward_quantity' => StockInward::where('is_active', true)->sum('quantity'),
            'inward_value' => StockInward::where('is_active', true)->sum('total_amount'),
            'outward_quantity' => StockOutward::where('is_active', true)->sum('quantity'),
            'outward_value' => StockOutward::where('is_active', true)->sum('total_amount'),
        ];

        return compact('stock', 'categoryReport', 'companyReport', 'movement');
    }

    public function settings()
    {
        return view('workspace.settings');
    }

    public function locations()
    {
        $locations = DB::table('stores')
            ->leftJoin('companies', 'companies.id', '=', 'stores.company_id')
            ->select('stores.name', 'stores.code', 'companies.name as company_name', 'stores.location', 'stores.is_active')
            ->orderBy('stores.name')
            ->paginate(20);

        return $this->recordsPage(
            'Locations',
            'MASTER DATA',
            'Manage the inventory locations configured for your stores.',
            $locations,
            [
                ['label' => 'Location', 'key' => 'name'],
                ['label' => 'Company / Brand', 'key' => 'company_name'],
                ['label' => 'Code', 'key' => 'code'],
                ['label' => 'Address / Area', 'key' => 'location'],
                ['label' => 'Status', 'key' => 'is_active', 'type' => 'active'],
            ],
            [['label' => 'Locations', 'value' => DB::table('stores')->count(), 'icon' => 'icon-building', 'tone' => 'blue']],
            'locations.create',
            'Add Location'
        );
    }

    public function createLocation()
    {
        $companies = Company::where('is_active', true)->orderBy('name')->get();

        return view('workspace.location-create', compact('companies'));
    }

    public function storeLocation(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:stores,code',
            'company_id' => 'required|integer|exists:companies,id,is_active,1',
            'location' => 'nullable|string|max:255',
        ]);

        DB::table('stores')->insert([
            'store_code' => trim($validated['code']),
            'name' => trim($validated['name']),
            'code' => trim($validated['code']),
            'company_id' => $validated['company_id'],
            'location' => isset($validated['location']) ? trim($validated['location']) : null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('locations.index')
            ->with('success', 'Location added.');
    }

    public function openingStock()
    {
        $products = Product::with(['category', 'unit'])
            ->where('opening_stock', '>', 0)
            ->orderBy('name')
            ->paginate(20);

        return $this->recordsPage(
            'Opening Stock',
            'INVENTORY',
            'Starting inventory quantities recorded for each product.',
            $products,
            [
                ['label' => 'Product', 'key' => 'name'],
                ['label' => 'Code', 'key' => 'product_code'],
                ['label' => 'Category', 'key' => 'category.name'],
                ['label' => 'Opening Quantity', 'key' => 'opening_stock', 'type' => 'quantity'],
                ['label' => 'Unit', 'key' => 'unit.short_name'],
                ['label' => 'Current Quantity', 'key' => 'current_stock', 'type' => 'quantity'],
            ],
            [[
                'label' => 'Products with Opening Stock',
                'value' => Product::where('opening_stock', '>', 0)->count(),
                'icon' => 'icon-box',
                'tone' => 'blue',
            ], [
                'label' => 'Opening Units',
                'value' => number_format(Product::sum('opening_stock'), 2),
                'icon' => 'icon-tray-in',
                'tone' => 'green',
            ]]
        );
    }

    public function createOpeningStock()
    {
        $products = Product::where('is_active', true)
            ->with('unit')
            ->orderBy('name')
            ->get(['id', 'name', 'product_code', 'current_stock', 'unit_id']);
        $locations = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('workspace.opening-stock-create', compact('products', 'locations'));
    }

    public function storeOpeningStock(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id,is_active,1',
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'quantity' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $hasInitialStockSetup = (float) $product->opening_stock > 0
            || (float) $product->current_stock > 0
            || DB::table('stock_transactions')
                ->where('product_id', $product->id)
                ->whereIn('reference_type', ['opening_stock', 'initial_stock'])
                ->exists();

        if ($hasInitialStockSetup) {
            throw ValidationException::withMessages([
                'product_id' => 'Initial stock has already been set for this product. Use a stock adjustment or stock movement to correct the balance.',
            ]);
        }

        DB::transaction(function () use ($validated, $product) {
            $quantity = (float) $validated['quantity'];
            $storeBalance = (float) DB::table('stock_transactions')
                ->where('product_id', $product->id)
                ->where('store_id', $validated['store_id'])
                ->sum(DB::raw('quantity_in - quantity_out'));
            $newBalance = $storeBalance + $quantity;

            $product->opening_stock = $quantity;
            $product->current_stock = $quantity;
            $product->save();

            DB::table('stock_transactions')->insert([
                'store_id' => $validated['store_id'],
                'product_id' => $product->id,
                'transaction_type' => 'opening',
                'reference_type' => 'opening_stock',
                'reference_id' => null,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'balance_quantity' => $newBalance,
                'unit_price' => $product->purchase_price,
                'transaction_date' => $validated['transaction_date'],
                'remarks' => $validated['remarks'] ?: 'Initial stock setup',
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('opening-stock.index')
            ->with('success', 'Initial stock setup saved. Daily opening balances are derived from the previous closing stock.');
    }

    public function stockTransfers()
    {
        if (Schema::hasColumn('stock_transfers', 'product_id')) {
            $transfersQuery = DB::table('stock_transfers')
                ->join('products', 'products.id', '=', 'stock_transfers.product_id')
                ->select(
                    'stock_transfers.transfer_number',
                    'products.name as product_name',
                    'stock_transfers.from_location',
                    'stock_transfers.to_location',
                    'stock_transfers.quantity',
                    'stock_transfers.transfer_date'
                );
            if (Schema::hasColumn('stock_transfers', 'is_active')) {
                $transfersQuery->where('stock_transfers.is_active', true);
            }
            $transferCount = (clone $transfersQuery)->count();
            $transferQuantity = (clone $transfersQuery)->sum('stock_transfers.quantity');
        } else {
            $transfersQuery = DB::table('stock_transfers')
                ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
                ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
                ->join('stores as from_store', 'from_store.id', '=', 'stock_transfers.from_store_id')
                ->join('stores as to_store', 'to_store.id', '=', 'stock_transfers.to_store_id')
                ->where('stock_transfers.status', '<>', 'cancelled')
                ->select(
                    'stock_transfers.transfer_number',
                    'products.name as product_name',
                    'from_store.name as from_location',
                    'to_store.name as to_location',
                    'stock_transfer_items.quantity',
                    'stock_transfers.transfer_date'
                );
            $transferCount = DB::table('stock_transfers')->where('status', '<>', 'cancelled')->count();
            $transferQuantity = DB::table('stock_transfer_items')
                ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
                ->where('stock_transfers.status', '<>', 'cancelled')
                ->sum('stock_transfer_items.quantity');
        }

        $transfers = $transfersQuery
            ->orderBy('transfer_date', 'desc')
            ->paginate(20);

        return $this->recordsPage(
            'Stock Transfers',
            'INVENTORY',
            'Review recorded movements between inventory locations.',
            $transfers,
            [
                ['label' => 'Reference', 'key' => 'transfer_number'],
                ['label' => 'Product', 'key' => 'product_name'],
                ['label' => 'From', 'key' => 'from_location'],
                ['label' => 'To', 'key' => 'to_location'],
                ['label' => 'Quantity', 'key' => 'quantity', 'type' => 'quantity'],
                ['label' => 'Date', 'key' => 'transfer_date', 'type' => 'date'],
            ],
            [[
                'label' => 'Recorded Transfers',
                'value' => $transferCount,
                'icon' => 'icon-tray-in',
                'tone' => 'blue',
            ], [
                'label' => 'Units Transferred',
                'value' => number_format($transferQuantity, 2),
                'icon' => 'icon-box',
                'tone' => 'green',
            ]],
            'stock-transfers.create',
            'Record Stock Transfer'
        );
    }

    public function createStockTransfer()
    {
        $products = Product::where('is_active', true)
            ->where('current_stock', '>', 0)
            ->with('unit')
            ->orderBy('name')
            ->get(['id', 'name', 'product_code', 'current_stock', 'unit_id']);
        $locations = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('workspace.stock-transfer-create', compact('products', 'locations'));
    }

    public function storeStockTransfer(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'from_location' => 'required|string|max:120|exists:stores,name,is_active,1',
            'to_location' => 'required|string|max:120|different:from_location|exists:stores,name,is_active,1',
            'quantity' => 'required|numeric|min:0.01',
            'transfer_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
        ]);

        $legacyTransferSchema = Schema::hasTable('stock_transfer_items')
            && Schema::hasColumn('stock_transfers', 'from_store_id')
            && Schema::hasColumn('stock_transfers', 'to_store_id');

        if ($legacyTransferSchema) {
            $validated['from_store_id'] = DB::table('stores')
                ->where('name', trim($validated['from_location']))
                ->where('is_active', true)
                ->value('id');
            $validated['to_store_id'] = DB::table('stores')
                ->where('name', trim($validated['to_location']))
                ->where('is_active', true)
                ->value('id');

            if (!$validated['from_store_id'] || !$validated['to_store_id']) {
                throw ValidationException::withMessages([
                    'from_location' => 'Select two active locations.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $legacyTransferSchema) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);
            if (!$product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'Select an active product.',
                ]);
            }
            if ((float) $validated['quantity'] > (float) $product->current_stock) {
                throw ValidationException::withMessages([
                    'quantity' => 'Transfer quantity cannot exceed the product quantity on hand.',
                ]);
            }

            $transferNumber = 'TRF-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
            if ($legacyTransferSchema) {
                $transferId = DB::table('stock_transfers')->insertGetId([
                    'transfer_number' => $transferNumber,
                    'from_store_id' => $validated['from_store_id'],
                    'to_store_id' => $validated['to_store_id'],
                    'transfer_date' => $validated['transfer_date'],
                    'status' => 'completed',
                    'remarks' => $validated['remarks'] ?? null,
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('stock_transfer_items')->insert([
                    'stock_transfer_id' => $transferId,
                    'product_id' => $product->id,
                    'quantity' => $validated['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                StockTransfer::create([
                    'product_id' => $product->id,
                    'transfer_number' => $transferNumber,
                    'transfer_date' => $validated['transfer_date'],
                    'from_location' => trim($validated['from_location']),
                    'to_location' => trim($validated['to_location']),
                    'quantity' => $validated['quantity'],
                    'remarks' => $validated['remarks'] ?? null,
                    'is_active' => true,
                ]);
            }
        });

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', 'Stock transfer recorded.');
    }

    public function stockAdjustments()
    {
        if (Schema::hasColumn('stock_adjustments', 'adjustment_number')) {
            $adjustmentQuery = DB::table('stock_adjustments')
                ->join('products', 'products.id', '=', 'stock_adjustments.product_id')
                ->select(
                    'stock_adjustments.adjustment_number as reference_number',
                    'products.name as product_name',
                    'stock_adjustments.type as adjustment_type',
                    DB::raw("CASE WHEN LOWER(stock_adjustments.type) LIKE '%decreas%' OR LOWER(stock_adjustments.type) LIKE '%out%' THEN -stock_adjustments.quantity ELSE stock_adjustments.quantity END as adjustment_quantity"),
                    'stock_adjustments.reason',
                    'stock_adjustments.adjustment_date'
                );
            if (Schema::hasColumn('stock_adjustments', 'is_active')) {
                $adjustmentQuery->where('stock_adjustments.is_active', true);
            }
            $adjustmentCount = (clone $adjustmentQuery)->count();
            $netAdjustmentQuery = DB::table('stock_adjustments')
                ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(type) LIKE '%decreas%' OR LOWER(type) LIKE '%out%' THEN -quantity ELSE quantity END), 0) as net");
            if (Schema::hasColumn('stock_adjustments', 'is_active')) {
                $netAdjustmentQuery->where('is_active', true);
            }
            $netAdjustment = $netAdjustmentQuery->value('net');
        } else {
            $adjustmentQuery = DB::table('stock_adjustments')
                ->join('products', 'products.id', '=', 'stock_adjustments.product_id')
                ->select(
                    'stock_adjustments.reference_number',
                    'products.name as product_name',
                        DB::raw("CASE WHEN stock_adjustments.adjustment_quantity < 0 THEN 'Decrease' ELSE 'Increase' END as adjustment_type"),
                    'stock_adjustments.adjustment_quantity',
                    'stock_adjustments.reason',
                    'stock_adjustments.adjustment_date'
                );
            $adjustmentQuery->where('stock_adjustments.is_active', true);
            $adjustmentCount = (clone $adjustmentQuery)->count();
            $netAdjustment = DB::table('stock_adjustments')
                ->where('is_active', true)
                ->sum('adjustment_quantity');
        }

        $adjustments = $adjustmentQuery
            ->orderBy('adjustment_date', 'desc')
            ->paginate(20);

        return $this->recordsPage(
            'Stock Adjustments',
            'INVENTORY',
            'Review recorded corrections to on-hand product quantities.',
            $adjustments,
            [
                ['label' => 'Reference', 'key' => 'reference_number'],
                ['label' => 'Product', 'key' => 'product_name'],
                ['label' => 'Type', 'key' => 'adjustment_type'],
                ['label' => 'Change', 'key' => 'adjustment_quantity', 'type' => 'quantity'],
                ['label' => 'Reason', 'key' => 'reason'],
                ['label' => 'Date', 'key' => 'adjustment_date', 'type' => 'date'],
            ],
            [[
                'label' => 'Recorded Adjustments',
                'value' => $adjustmentCount,
                'icon' => 'icon-settings',
                'tone' => 'orange',
            ], [
                'label' => 'Net Quantity Change',
                'value' => number_format($netAdjustment, 2),
                'icon' => 'icon-chart',
                'tone' => 'purple',
            ]],
            'stock-adjustments.create',
            'Record Stock Adjustment'
        );
    }

    public function createStockAdjustment()
    {
        $products = Product::where('is_active', true)
            ->with('unit')
            ->orderBy('name')
            ->get(['id', 'name', 'product_code', 'current_stock', 'unit_id']);
        $locations = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $requiresStore = Schema::hasColumn('stock_adjustments', 'store_id');

        return view('workspace.stock-adjustment-create', compact('products', 'locations', 'requiresStore'));
    }

    public function storeStockAdjustment(Request $request)
    {
        $rules = [
            'product_id' => 'required|integer|exists:products,id',
            'adjustment_type' => 'required|in:increase,decrease',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'adjustment_date' => 'required|date',
        ];
        if (Schema::hasColumn('stock_adjustments', 'store_id')) {
            $rules['store_id'] = 'required|integer|exists:stores,id,is_active,1';
        }
        $validated = $request->validate($rules);
        $legacyAdjustmentSchema = Schema::hasColumn('stock_adjustments', 'adjustment_number');

        DB::transaction(function () use ($validated, $legacyAdjustmentSchema) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);
            if (!$product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'Select an active product.',
                ]);
            }

            $quantity = (float) $validated['quantity'];
            $quantityBefore = (float) $product->current_stock;
            $adjustmentQuantity = $validated['adjustment_type'] === 'decrease' ? -$quantity : $quantity;
            $quantityAfter = $quantityBefore + $adjustmentQuantity;

            $storeBalance = null;
            if (isset($validated['store_id']) && Schema::hasTable('stock_transactions')) {
                $storeBalance = (float) DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->where('store_id', $validated['store_id'])
                    ->lockForUpdate()
                    ->sum(DB::raw('quantity_in - quantity_out'));
            }

            if ($quantityAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'A decrease cannot exceed the product quantity on hand.',
                ]);
            }
            if ($storeBalance !== null
                && $validated['adjustment_type'] === 'decrease'
                && $quantity > $storeBalance) {
                throw ValidationException::withMessages([
                    'quantity' => 'A decrease cannot exceed the selected location quantity on hand (' . number_format($storeBalance, 2) . ').',
                ]);
            }

            $product->current_stock = $quantityAfter;
            $product->save();

            $reference = 'ADJ-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
            if ($legacyAdjustmentSchema) {
                $adjustmentData = [
                    'adjustment_number' => $reference,
                    'product_id' => $product->id,
                    'store_id' => $validated['store_id'],
                    'type' => $validated['adjustment_type'],
                    'quantity' => $quantity,
                    'reason' => $validated['reason'],
                    'adjustment_date' => $validated['adjustment_date'],
                ];
                if (Schema::hasColumn('stock_adjustments', 'remarks')) {
                    $adjustmentData['remarks'] = $validated['reason'];
                }
                if (Schema::hasColumn('stock_adjustments', 'created_by')) {
                    $adjustmentData['created_by'] = Auth::id();
                }
                $adjustmentData['created_at'] = now();
                $adjustmentData['updated_at'] = now();
                $adjustmentId = DB::table('stock_adjustments')->insertGetId($adjustmentData);
            } else {
                $adjustmentId = StockAdjustment::create([
                    'product_id' => $product->id,
                    'reference_number' => $reference,
                    'adjustment_date' => $validated['adjustment_date'],
                    'quantity_before' => $quantityBefore,
                    'adjustment_quantity' => $adjustmentQuantity,
                    'quantity_after' => $quantityAfter,
                    'reason' => $validated['reason'],
                    'is_active' => true,
                ])->id;
            }

            if (isset($validated['store_id']) && Schema::hasTable('stock_transactions')) {
                DB::table('stock_transactions')->insert([
                    'store_id' => $validated['store_id'],
                    'product_id' => $product->id,
                    'transaction_type' => $validated['adjustment_type'] === 'increase' ? 'adjustment_in' : 'adjustment_out',
                    'reference_type' => 'stock_adjustment',
                    'reference_id' => $adjustmentId,
                    'quantity_in' => $validated['adjustment_type'] === 'increase' ? $quantity : 0,
                    'quantity_out' => $validated['adjustment_type'] === 'decrease' ? $quantity : 0,
                    'balance_quantity' => ($storeBalance ?? 0) + $adjustmentQuantity,
                    'unit_price' => $product->purchase_price,
                    'transaction_date' => $validated['adjustment_date'],
                    'remarks' => $validated['reason'],
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('stock-adjustments.index')
            ->with('success', 'Stock adjustment saved and on-hand quantity updated.');
    }

    public function stockMovement(Request $request)
    {
        $movementRows = DB::table('stock_inwards')
            ->join('products', 'products.id', '=', 'stock_inwards.product_id')
            ->where('stock_inwards.is_active', true)
            ->selectRaw("'Stock Inward' as movement_type, stock_inwards.invoice_number as reference, products.name as product_name, stock_inwards.quantity as quantity, stock_inwards.total_amount as amount, stock_inwards.inward_date as movement_date")
            ->unionAll(
                DB::table('stock_outwards')
                    ->join('products', 'products.id', '=', 'stock_outwards.product_id')
                    ->where('stock_outwards.is_active', true)
                    ->selectRaw("'Stock Outward' as movement_type, stock_outwards.reference_number as reference, products.name as product_name, stock_outwards.quantity as quantity, stock_outwards.total_amount as amount, stock_outwards.outward_date as movement_date")
            )
            ->unionAll($this->transferMovementQuery())
            ->unionAll($this->adjustmentMovementQuery());

        $movementsQuery = DB::query()->fromSub($movementRows, 'movements');
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $movementsQuery->where(function ($query) use ($search) {
                $query->where('reference', 'like', '%' . $search . '%')
                    ->orWhere('product_name', 'like', '%' . $search . '%')
                    ->orWhere('movement_type', 'like', '%' . $search . '%');
            });
        }

        $movements = $movementsQuery
            ->orderBy('movement_date', 'desc')
            ->paginate(20)
            ->appends($request->query());

        return $this->recordsPage(
            'Stock Movement',
            'INVENTORY',
            'A unified chronological view of received, issued, transferred, and adjusted stock.',
            $movements,
            [
                ['label' => 'Type', 'key' => 'movement_type'],
                ['label' => 'Reference', 'key' => 'reference'],
                ['label' => 'Product', 'key' => 'product_name'],
                ['label' => 'Quantity', 'key' => 'quantity', 'type' => 'quantity'],
                ['label' => 'Value', 'key' => 'amount', 'type' => 'currency'],
                ['label' => 'Date', 'key' => 'movement_date', 'type' => 'date'],
            ],
            [[
                'label' => 'Inward Records',
                'value' => StockInward::where('is_active', true)->count(),
                'icon' => 'icon-tray-in',
                'tone' => 'green',
            ], [
                'label' => 'Outward Records',
                'value' => StockOutward::where('is_active', true)->count(),
                'icon' => 'icon-tray-out',
                'tone' => 'orange',
            ], [
                'label' => 'Transfers & Adjustments',
                'value' => $this->transferCount() + $this->adjustmentCount(),
                'icon' => 'icon-chart',
                'tone' => 'purple',
            ]]
        );
    }

    public function purchaseReports()
    {
        $purchases = StockInward::with(['product', 'supplier'])
            ->where('is_active', true)
            ->orderBy('inward_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return $this->recordsPage(
            'Purchase Reports',
            'REPORTS',
            'Purchases recorded through active stock inward transactions.',
            $purchases,
            [
                ['label' => 'Invoice', 'key' => 'invoice_number'],
                ['label' => 'Product', 'key' => 'product.name'],
                ['label' => 'Supplier', 'key' => 'supplier.name'],
                ['label' => 'Quantity', 'key' => 'quantity', 'type' => 'quantity'],
                ['label' => 'Purchase Price', 'key' => 'purchase_price', 'type' => 'currency'],
                ['label' => 'Total', 'key' => 'total_amount', 'type' => 'currency'],
                ['label' => 'Date', 'key' => 'inward_date', 'type' => 'date'],
            ],
            [[
                'label' => 'Purchase Transactions',
                'value' => StockInward::where('is_active', true)->count(),
                'icon' => 'icon-tray-in',
                'tone' => 'blue',
            ], [
                'label' => 'Total Purchase Value',
                'value' => '₹' . number_format(StockInward::where('is_active', true)->sum('total_amount'), 2),
                'icon' => 'icon-arrow-down',
                'tone' => 'purple',
            ]]
        );
    }

    public function issueReports()
    {
        $issues = StockOutward::with('product')
            ->where('is_active', true)
            ->orderBy('outward_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return $this->recordsPage(
            'Issue Reports',
            'REPORTS',
            'Issues and recipients recorded through active stock outward transactions.',
            $issues,
            [
                ['label' => 'Reference', 'key' => 'reference_number'],
                ['label' => 'Product', 'key' => 'product.name'],
                ['label' => 'Recipient', 'key' => 'issued_to'],
                ['label' => 'Quantity', 'key' => 'quantity', 'type' => 'quantity'],
                ['label' => 'Selling Price', 'key' => 'selling_price', 'type' => 'currency'],
                ['label' => 'Total', 'key' => 'total_amount', 'type' => 'currency'],
                ['label' => 'Date', 'key' => 'outward_date', 'type' => 'date'],
            ],
            [[
                'label' => 'Issue Transactions',
                'value' => StockOutward::where('is_active', true)->count(),
                'icon' => 'icon-tray-out',
                'tone' => 'orange',
            ], [
                'label' => 'Total Issued Value',
                'value' => '₹' . number_format(StockOutward::where('is_active', true)->sum('total_amount'), 2),
                'icon' => 'icon-arrow-up',
                'tone' => 'green',
            ]]
        );
    }

    public function stockValuation()
    {
        $products = Product::with(['category', 'company', 'unit'])
            ->where('is_active', true)
            ->select('products.*')
            ->selectRaw('current_stock * purchase_price as stock_value')
            ->orderBy('name')
            ->paginate(20);

        return $this->recordsPage(
            'Stock Valuation',
            'REPORTS',
            'Current inventory quantities valued at each product purchase price.',
            $products,
            [
                ['label' => 'Product', 'key' => 'name'],
                ['label' => 'Code', 'key' => 'product_code'],
                ['label' => 'Category', 'key' => 'category.name'],
                ['label' => 'Company / Brand', 'key' => 'company.name'],
                ['label' => 'Quantity', 'key' => 'current_stock', 'type' => 'quantity'],
                ['label' => 'Unit Cost', 'key' => 'purchase_price', 'type' => 'currency'],
                ['label' => 'Stock Value', 'key' => 'stock_value', 'type' => 'currency'],
            ],
            [[
                'label' => 'Products Valued',
                'value' => Product::where('is_active', true)->count(),
                'icon' => 'icon-box',
                'tone' => 'blue',
            ], [
                'label' => 'Total Stock Value',
                'value' => '₹' . number_format(Product::where('is_active', true)->sum(DB::raw('current_stock * purchase_price')), 2),
                'icon' => 'icon-chart',
                'tone' => 'green',
            ]]
        );
    }

    public function users(Request $request)
    {
        $query = DB::table('users')
            ->select('name', 'email', 'role', 'is_active', 'last_login_at')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $users = $query->paginate(20)->appends($request->query());

        return $this->recordsPage(
            'Users',
            'SETTINGS',
            'Search registered accounts and review access status and recent sign-ins.',
            $users,
            [
                ['label' => 'Name', 'key' => 'name'],
                ['label' => 'Email', 'key' => 'email'],
                ['label' => 'Role', 'key' => 'role'],
                ['label' => 'Status', 'key' => 'is_active', 'type' => 'active'],
                ['label' => 'Last Sign-in', 'key' => 'last_login_at', 'type' => 'date'],
            ],
            [['label' => 'Registered Users', 'value' => DB::table('users')->count(), 'icon' => 'icon-users', 'tone' => 'blue']]
        );
    }

    public function activityLog(Request $request)
    {
        $filters = $request->validate([
            'search' => 'nullable|string|max:150',
            'action' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d|after_or_equal:from',
        ]);

        if (Schema::hasColumn('activity_logs', 'actor_name')) {
            $activityQuery = DB::table('activity_logs')
                ->select('actor_name', 'action', 'subject', 'description', 'created_at');
        } else {
            $activityQuery = DB::table('activity_logs')
                ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
                ->select(
                    DB::raw("COALESCE(users.name, 'System') as actor_name"),
                    'activity_logs.action',
                    'activity_logs.module as subject',
                    'activity_logs.description',
                    'activity_logs.created_at'
                );
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $activityQuery->where(function ($query) use ($search) {
                $query->where('activity_logs.action', 'like', '%' . $search . '%')
                    ->orWhere('activity_logs.description', 'like', '%' . $search . '%');
                if (Schema::hasColumn('activity_logs', 'actor_name')) {
                    $query->orWhere('activity_logs.actor_name', 'like', '%' . $search . '%')
                        ->orWhere('activity_logs.subject', 'like', '%' . $search . '%');
                } else {
                    $query->orWhere('users.name', 'like', '%' . $search . '%')
                        ->orWhere('activity_logs.module', 'like', '%' . $search . '%');
                }
            });
        }
        if (!empty($filters['action'])) {
            $activityQuery->where('activity_logs.action', $filters['action']);
        }
        if (!empty($filters['from'])) {
            $activityQuery->whereDate('activity_logs.created_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $activityQuery->whereDate('activity_logs.created_at', '<=', $filters['to']);
        }

        $activity = $activityQuery
            ->orderBy('activity_logs.created_at', 'desc')
            ->paginate(20);
        $activity->appends($request->query());
        $actions = DB::table('activity_logs')
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return $this->recordsPage(
            'Activity Log',
            'SETTINGS',
            'Review and filter recorded account and system activity.',
            $activity,
            [
                ['label' => 'User', 'key' => 'actor_name'],
                ['label' => 'Action', 'key' => 'action'],
                ['label' => 'Record', 'key' => 'subject'],
                ['label' => 'Details', 'key' => 'description'],
                ['label' => 'Date', 'key' => 'created_at', 'type' => 'date'],
            ],
            [['label' => 'Activity Entries', 'value' => DB::table('activity_logs')->count(), 'icon' => 'icon-chart', 'tone' => 'purple']],
            null,
            null,
            ['actions' => $actions]
        );
    }

    public function generalSettings()
    {
        $settings = DB::table('system_settings')
            ->whereIn('key', ['business_name', 'business_email', 'business_phone', 'business_address'])
            ->pluck('value', 'key')
            ->all();

        return view('workspace.settings', [
            'settings' => array_merge([
                'business_name' => 'Aayojan Ai Inventory System',
                'business_email' => '',
                'business_phone' => '',
                'business_address' => '',
            ], $settings),
        ]);
    }

    public function updateGeneralSettings(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:150',
            'business_email' => 'nullable|email|max:255',
            'business_phone' => 'nullable|string|max:30',
            'business_address' => 'nullable|string|max:500',
        ]);

        foreach ($validated as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }

        ActivityLogger::log(
            'Settings updated',
            'General settings',
            'Updated the organization profile details.'
        );

        return redirect()
            ->route('general-settings.index')
            ->with('success', 'General settings saved.');
    }

    private function recordsPage($title, $eyebrow, $description, $rows, array $columns, array $summary, $createRoute = null, $createLabel = null, array $pageOptions = [])
    {
        $showOpeningStockAction = $title === 'Opening Stock';

        return view('workspace.records', compact(
            'title',
            'eyebrow',
            'description',
            'rows',
            'columns',
            'summary',
            'showOpeningStockAction',
            'createRoute',
            'createLabel',
            'pageOptions'
        ));
    }

    private function transferMovementQuery()
    {
        if (Schema::hasColumn('stock_transfers', 'product_id')) {
            return DB::table('stock_transfers')
                ->join('products', 'products.id', '=', 'stock_transfers.product_id')
                ->where('stock_transfers.is_active', true)
                ->selectRaw("'Stock Transfer' as movement_type, stock_transfers.transfer_number as reference, products.name as product_name, stock_transfers.quantity as quantity, 0 as amount, stock_transfers.transfer_date as movement_date");
        }

        return DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
            ->where('stock_transfers.status', '<>', 'cancelled')
            ->selectRaw("'Stock Transfer' as movement_type, stock_transfers.transfer_number as reference, products.name as product_name, stock_transfer_items.quantity as quantity, 0 as amount, stock_transfers.transfer_date as movement_date");
    }

    private function adjustmentMovementQuery()
    {
        if (Schema::hasColumn('stock_adjustments', 'adjustment_number')) {
            return DB::table('stock_adjustments')
                ->join('products', 'products.id', '=', 'stock_adjustments.product_id')
                ->selectRaw("'Stock Adjustment' as movement_type, stock_adjustments.adjustment_number as reference, products.name as product_name, CASE WHEN LOWER(stock_adjustments.type) LIKE '%decreas%' OR LOWER(stock_adjustments.type) LIKE '%out%' THEN -stock_adjustments.quantity ELSE stock_adjustments.quantity END as quantity, 0 as amount, stock_adjustments.adjustment_date as movement_date");
        }

        return DB::table('stock_adjustments')
            ->join('products', 'products.id', '=', 'stock_adjustments.product_id')
            ->where('stock_adjustments.is_active', true)
            ->selectRaw("'Stock Adjustment' as movement_type, stock_adjustments.reference_number as reference, products.name as product_name, stock_adjustments.adjustment_quantity as quantity, 0 as amount, stock_adjustments.adjustment_date as movement_date");
    }

    private function transferCount()
    {
        if (Schema::hasColumn('stock_transfers', 'status')) {
            return DB::table('stock_transfers')->where('status', '<>', 'cancelled')->count();
        }

        return DB::table('stock_transfers')->where('is_active', true)->count();
    }

    private function adjustmentCount()
    {
        return DB::table('stock_adjustments')->count();
    }
}
