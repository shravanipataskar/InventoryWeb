<?php

namespace App\Http\Controllers;

use App\Category;
use App\Company;
use App\Product;
use App\StockAdjustment;
use App\StockInward;
use App\StockOutward;
use App\User;
use App\Services\InventoryReportsService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            $query->whereRaw('current_stock > 0 AND current_stock <= COALESCE(NULLIF(reorder_level, 0), minimum_stock)');
        } elseif ($request->input('stock') === 'out') {
            $query->where('current_stock', '<=', 0);
        } elseif ($request->input('stock') === 'available') {
            $query->whereRaw('current_stock > COALESCE(NULLIF(reorder_level, 0), minimum_stock)');
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
                ->whereRaw('current_stock > 0 AND current_stock <= COALESCE(NULLIF(reorder_level, 0), minimum_stock)')
                ->count(),
            'out_of_stock' => (clone $summaryQuery)->where('current_stock', '<=', 0)->count(),
        ];

        return view('workspace.current-stock', compact('products', 'summary'));
    }

    public function reports(Request $request, InventoryReportsService $reports)
    {
        $today = now()->toDateString();
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'as_of_date' => 'nullable|date',
            'location_id' => 'nullable|integer|exists:stores,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'stock_status' => 'nullable|in:all,low,out,healthy',
            'movement_type' => 'nullable|in:opening,purchase_in,sales_out,issue_out,transfer_in,transfer_out,adjustment_in,adjustment_out',
            'search' => 'nullable|string|max:100',
            'tab' => 'nullable|in:overview,stock-movement,stock-valuation,low-reorder,purchase-inward,outward,transfer,adjustment',
            'page' => 'nullable|integer|min:1',
        ]);
        $filters = [
            'date_from' => $validated['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? $today,
            'as_of_date' => $validated['as_of_date'] ?? $today,
            'location_id' => $validated['location_id'] ?? '',
            'category_id' => $validated['category_id'] ?? '',
            'company_id' => $validated['company_id'] ?? '',
            'product_id' => $validated['product_id'] ?? '',
            'stock_status' => $validated['stock_status'] ?? 'all',
            'movement_type' => $validated['movement_type'] ?? '',
            'search' => trim($validated['search'] ?? ''),
        ];
        $tab = $validated['tab'] ?? 'overview';
        $overview = $reports->overview($filters);
        $rows = $tab === 'overview' ? null : $reports->paginatedReport($tab, $filters)->appends($request->query());
        $options = $reports->options();

        return view('workspace.reports', compact('filters', 'tab', 'overview', 'rows', 'options'));
    }

    public function downloadReports(Request $request, InventoryReportsService $reports)
    {
        $validated = $request->validate([
            'type' => 'required|in:current-report,stock-details,stock-movement,reorder,stock-valuation',
            'format' => 'required|in:csv,excel',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'as_of_date' => 'nullable|date',
            'location_id' => 'nullable|integer|exists:stores,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'stock_status' => 'nullable|in:all,low,out,healthy',
            'movement_type' => 'nullable|in:opening,purchase_in,sales_out,issue_out,transfer_in,transfer_out,adjustment_in,adjustment_out',
            'search' => 'nullable|string|max:100',
        ]);
        $today = now()->toDateString();
        $filters = [
            'date_from' => $validated['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? $today,
            'as_of_date' => $validated['as_of_date'] ?? $today,
            'location_id' => $validated['location_id'] ?? '',
            'category_id' => $validated['category_id'] ?? '',
            'company_id' => $validated['company_id'] ?? '',
            'product_id' => $validated['product_id'] ?? '',
            'stock_status' => $validated['stock_status'] ?? 'all',
            'movement_type' => $validated['movement_type'] ?? '',
            'search' => trim($validated['search'] ?? ''),
        ];
        $type = $validated['type'];
        $format = $validated['format'];
        $query = $reports->exportQuery($type, $filters);
        $headers = $reports->exportHeaders($type);
        $extension = $format === 'excel' ? 'xls' : 'csv';
        $contentType = $format === 'excel' ? 'application/vnd.ms-excel; charset=UTF-8' : 'text/csv; charset=UTF-8';

        return response()->streamDownload(function () use ($query, $headers, $reports, $type, $format) {
            if ($format === 'excel') {
                echo "\xEF\xBB\xBF<table><thead><tr>";
                foreach ($headers as $header) {
                    echo '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
                }
                echo '</tr></thead><tbody>';
                $query->chunk(500, function ($batch) use ($reports, $type) {
                    foreach ($batch as $row) {
                        echo '<tr>';
                        foreach ($reports->exportValues($type, $row) as $value) {
                            echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
                        }
                        echo '</tr>';
                    }
                });
                echo '</tbody></table>';

                return;
            }

            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);
            $query->chunk(500, function ($batch) use ($output, $reports, $type) {
                foreach ($batch as $row) {
                    $values = array_map(function ($value) {
                        if (is_string($value) && preg_match('/^\s*[=+\-@]/', $value)) {
                            return "'" . $value;
                        }

                        return $value;
                    }, $reports->exportValues($type, $row));
                    fputcsv($output, $values);
                }
            });
            fclose($output);
        }, 'inventory-' . $type . '-' . now()->format('Y-m-d-His') . '.' . $extension, [
            'Content-Type' => $contentType,
        ]);
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
        $initialStock = DB::table('stock_transactions')
            ->join('products', 'products.id', '=', 'stock_transactions.product_id')
            ->leftJoin('stores', 'stores.id', '=', 'stock_transactions.store_id')
            ->leftJoin('users', 'users.id', '=', 'stock_transactions.created_by')
            ->whereIn('stock_transactions.reference_type', ['opening_stock', 'initial_stock'])
            ->select(
                'stock_transactions.reference_number',
                'products.name as product_name',
                'products.product_code',
                'stores.name as location_name',
                'stock_transactions.quantity_in as opening_quantity',
                'stock_transactions.unit_price',
                DB::raw('(stock_transactions.quantity_in * stock_transactions.unit_price) as opening_value'),
                'stock_transactions.transaction_date',
                'users.name as created_by_name',
                'stock_transactions.created_at',
                'stock_transactions.remarks'
            )
            ->orderBy('stock_transactions.transaction_date', 'desc')
            ->orderBy('stock_transactions.id', 'desc')
            ->paginate(20);

        return $this->recordsPage(
            'Opening Stock',
            'INVENTORY',
            'One-time starting inventory recorded by product and location.',
            $initialStock,
            [
                ['label' => 'Reference No.', 'key' => 'reference_number'],
                ['label' => 'Product', 'key' => 'product_name'],
                ['label' => 'Code', 'key' => 'product_code'],
                ['label' => 'Location', 'key' => 'location_name'],
                ['label' => 'Opening Quantity', 'key' => 'opening_quantity', 'type' => 'quantity'],
                ['label' => 'Unit Purchase Rate', 'key' => 'unit_price', 'type' => 'currency'],
                ['label' => 'Opening Value', 'key' => 'opening_value', 'type' => 'currency'],
                ['label' => 'Opening Date', 'key' => 'transaction_date', 'type' => 'date'],
                ['label' => 'Created By', 'key' => 'created_by_name'],
                ['label' => 'Created At', 'key' => 'created_at', 'type' => 'datetime'],
                ['label' => 'Remarks', 'key' => 'remarks'],
            ],
            [[
                'label' => 'Opening Stock Entries',
                'value' => DB::table('stock_transactions')
                    ->whereIn('reference_type', ['opening_stock', 'initial_stock'])
                    ->count(),
                'icon' => 'icon-box',
                'tone' => 'blue',
            ], [
                'label' => 'Opening Value',
                'value' => '₹' . number_format((float) DB::table('stock_transactions')
                    ->whereIn('reference_type', ['opening_stock', 'initial_stock'])
                    ->sum(DB::raw('quantity_in * unit_price')), 2),
                'icon' => 'icon-tray-in',
                'tone' => 'green',
            ]]
        );
    }

    public function createOpeningStock()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $products = Product::where('is_active', true)
            ->with('unit', 'category')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'product_code',
                'category_id',
                'purchase_price',
                'current_stock',
                'unit_id'
            ]);
            

        $locations = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('workspace.opening-stock-create', compact('categories', 'products', 'locations'));
    }

    public function storeOpeningStock(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id,is_active,1'],
            'product_id' => ['required', 'integer', 'exists:products,id,is_active,1', function ($attribute, $value, $fail) use ($request) {
                $categoryId = $request->input('category_id');
                if ($categoryId === null || $categoryId === '') {
                    return;
                }

                $product = Product::where('id', $value)->where('is_active', true)->first();
                if (!$product || (int) $product->category_id !== (int) $categoryId) {
                    $fail('Selected product does not belong to the selected category.');
                }
            }],
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'product_id' => 'required|integer|exists:products,id,is_active,1',
            'quantity' => 'required|numeric|min:0.01',
            'unit_purchase_rate' => 'required|numeric|min:0',
            'transaction_date' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
        ]);

        $product = Product::findOrFail($validated['product_id']);
        if (!empty($validated['category_id']) && (int) $product->category_id !== (int) $validated['category_id']) {
            throw ValidationException::withMessages([
                'product_id' => 'Selected product does not belong to the selected category.',
            ]);
        }

        $duplicateExists = DB::table('stock_transactions')
            ->where('product_id', $product->id)
            ->where('store_id', $validated['store_id'])
            ->where('reference_type', 'opening_stock')
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'product_id' => 'Initial stock has already been configured for this product at this location. Use Stock Adjustment to make corrections.',
            ]);
        }

        $storeId = $validated['store_id'] ?? DB::table('stores')->where('is_active', true)->value('id');

        DB::transaction(function () use ($validated, $product, $storeId) {
            $quantity = (float) $validated['quantity'];
            $unitPurchaseRate = (float) $validated['unit_purchase_rate'];
            $storeBalance = (float) DB::table('stock_transactions')
                ->where('product_id', $product->id)
                ->where('store_id', $validated['store_id'])
                ->sum(DB::raw('quantity_in - quantity_out'));
            $storeBalance = 0.0;
            if ($storeId) {
                $storeBalance = (float) DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->where('store_id', $storeId)
                    ->sum(DB::raw('quantity_in - quantity_out'));
            }
            $newBalance = $storeBalance + $quantity;
            $referenceNumber = 'OPEN-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));

            $product->opening_stock = $quantity;
            $product->current_stock = $quantity;
            $product->purchase_price = $product->purchase_price ?: $unitPurchaseRate;
            $product->save();

            DB::table('stock_transactions')->insert([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'transaction_type' => 'opening',
                'reference_type' => 'opening_stock',
                'reference_id' => null,
                'reference_number' => $referenceNumber,
                'quantity_in' => $quantity,
                'quantity_out' => 0,
                'balance_quantity' => $newBalance,
                'unit_price' => $unitPurchaseRate,
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
        if (Schema::hasTable('stock_transfer_items')
            && Schema::hasColumn('stock_transfers', 'from_store_id')
            && Schema::hasColumn('stock_transfers', 'to_store_id')) {
            $transfersQuery = DB::table('stock_transfers')
                ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
                ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
                ->join('stores as from_store', 'from_store.id', '=', 'stock_transfers.from_store_id')
                ->join('stores as to_store', 'to_store.id', '=', 'stock_transfers.to_store_id')
                ->where('stock_transfers.status', '<>', 'cancelled')
                ->select(
                    'stock_transfers.id',
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
        } else {
            $transfersQuery = DB::table('stock_transfers')
                ->join('products', 'products.id', '=', 'stock_transfers.product_id')
                ->select(
                    'stock_transfers.id',
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
                ['label' => 'Reference', 'key' => 'id', 'type' => 'transfer-link'],
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

    public function showStockTransfer($id)
    {
        $transfer = DB::table('stock_transfers')->where('id', $id)->first();
        abort_unless($transfer, 404);

        $transfer->from_location_label = isset($transfer->from_store_id)
            ? DB::table('stores')->where('id', $transfer->from_store_id)->value('name')
            : ($transfer->from_location ?? '—');
        $transfer->to_location_label = isset($transfer->to_store_id)
            ? DB::table('stores')->where('id', $transfer->to_store_id)->value('name')
            : ($transfer->to_location ?? '—');
        $transfer->requested_by_name = isset($transfer->created_by)
            ? DB::table('users')->where('id', $transfer->created_by)->value('name')
            : null;
        $transfer->approved_by_name = isset($transfer->approved_by)
            ? DB::table('users')->where('id', $transfer->approved_by)->value('name')
            : null;

        if (Schema::hasTable('stock_transfer_items')) {
            $itemColumns = [
                'products.name as product_name',
                'products.product_code',
                'categories.name as category_name',
                'units.short_name as unit_name',
                'stock_transfer_items.quantity',
            ];
            $itemColumns[] = Schema::hasColumn('products', 'sku')
                ? 'products.sku'
                : DB::raw('products.product_code as sku');
            $itemColumns[] = Schema::hasColumn('stock_transfer_items', 'remarks')
                ? 'stock_transfer_items.remarks'
                : DB::raw('NULL as remarks');
            $itemColumns[] = Schema::hasColumn('stock_transfer_items', 'source_before')
                ? 'stock_transfer_items.source_before'
                : DB::raw('NULL as source_before');
            $itemColumns[] = Schema::hasColumn('stock_transfer_items', 'destination_before')
                ? 'stock_transfer_items.destination_before'
                : DB::raw('NULL as destination_before');
            $items = DB::table('stock_transfer_items')
                ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->leftJoin('units', 'units.id', '=', 'products.unit_id')
                ->where('stock_transfer_items.stock_transfer_id', $id)
                ->select($itemColumns)
                ->get();
        } elseif (isset($transfer->product_id)) {
            $itemColumns = [
                'products.name as product_name',
                'products.product_code',
                'categories.name as category_name',
                'units.short_name as unit_name',
                'stock_transfers.quantity',
                'stock_transfers.remarks',
                DB::raw('NULL as source_before'),
                DB::raw('NULL as destination_before'),
            ];
            $itemColumns[] = Schema::hasColumn('products', 'sku')
                ? 'products.sku'
                : DB::raw('products.product_code as sku');
            $items = DB::table('stock_transfers')
                ->join('products', 'products.id', '=', 'stock_transfers.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->leftJoin('units', 'units.id', '=', 'products.unit_id')
                ->where('stock_transfers.id', $id)
                ->select($itemColumns)
                ->get();
        } else {
            $items = collect();
        }

        $movements = Schema::hasTable('stock_transactions')
            ? DB::table('stock_transactions')
                ->leftJoin('stores', 'stores.id', '=', 'stock_transactions.store_id')
                ->where('stock_transactions.reference_type', 'stock_transfer')
                ->where('stock_transactions.reference_id', $id)
                ->select(
                    'stock_transactions.transaction_type',
                    'stock_transactions.quantity_in',
                    'stock_transactions.quantity_out',
                    'stock_transactions.transaction_date',
                    'stores.name as location_name'
                )
                ->orderBy('stock_transactions.id')
                ->get()
            : collect();

        return view('workspace.stock-transfer-show', compact('transfer', 'items', 'movements'));
    }

    public function createStockTransfer()
    {
        $categories = Category::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $productColumns = ['id', 'name', 'product_code', 'category_id', 'current_stock', 'unit_id'];
        if (Schema::hasColumn('products', 'sku')) {
            $productColumns[] = 'sku';
        }
        $products = Product::where('is_active', true)
            ->where('current_stock', '>', 0)
            ->with(['unit', 'category'])
            ->orderBy('name')
            ->get($productColumns);
        $locations = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $approvers = User::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $stockBalances = [];
        if (Schema::hasTable('stock_transactions')) {
            $balances = DB::table('stock_transactions')
                ->select('product_id', 'store_id')
                ->selectRaw('SUM(quantity_in - quantity_out) as quantity')
                ->groupBy('product_id', 'store_id')
                ->get();

            foreach ($balances as $balance) {
                $stockBalances[$balance->product_id][$balance->store_id] = (float) $balance->quantity;
            }
        }

        return view('workspace.stock-transfer-create', compact(
            'categories',
            'products',
            'locations',
            'stockBalances',
            'approvers'
        ));
    }

    public function storeStockTransfer(Request $request)
    {
        if (!$request->has('items') && $request->has('product_id')) {
            $product = Product::find($request->input('product_id'));
            $request->merge([
                'transfer_reason' => $request->input('transfer_reason', 'Stock Replenishment'),
                'items' => [[
                    'category_id' => $product ? $product->category_id : null,
                    'product_id' => $request->input('product_id'),
                    'quantity' => $request->input('quantity'),
                    'remarks' => $request->input('remarks'),
                ]],
            ]);
        }

        $validated = $request->validate([
            'from_location' => 'required|string|max:120|exists:stores,name,is_active,1',
            'to_location' => 'required|string|max:120|different:from_location|exists:stores,name,is_active,1',
            'transfer_date' => 'required|date',
            'transfer_reason' => 'required|in:Stock Replenishment,Department Requirement,Hall Requirement,Branch Requirement,Customer/Project Requirement,Overstock Balancing,Location Reorganization,Other',
            'reference_no' => 'nullable|string|max:100',
            'approved_by' => 'nullable|integer|exists:users,id,is_active,1',
            'remarks' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.category_id' => 'required|integer|exists:categories,id,is_active,1',
            'items.*.product_id' => 'required|integer|distinct|exists:products,id,is_active,1',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.remarks' => 'nullable|string|max:1000',
            'submission_key' => ['nullable', 'string', 'uuid', Rule::unique('stock_transfers', 'submission_key')],
        ]);

        if (!Schema::hasTable('stock_transfer_items')
            || !Schema::hasColumn('stock_transfers', 'from_store_id')
            || !Schema::hasColumn('stock_transfers', 'to_store_id')) {
            throw ValidationException::withMessages([
                'items' => 'Stock Transfer setup is incomplete. Apply the Stock Transfer database migration first.',
            ]);
        }

        $fromStoreId = DB::table('stores')->where('name', $validated['from_location'])->where('is_active', true)->value('id');
        $toStoreId = DB::table('stores')->where('name', $validated['to_location'])->where('is_active', true)->value('id');
        if (!$fromStoreId || !$toStoreId || $fromStoreId == $toStoreId) {
            throw ValidationException::withMessages([
                'from_location' => 'From Location and To Location must be different active locations.',
            ]);
        }
        $validated['from_store_id'] = $fromStoreId;
        $validated['to_store_id'] = $toStoreId;
        if (!Schema::hasTable('stock_transactions')) {
            throw ValidationException::withMessages([
                'items' => 'Location-based stock ledger is unavailable. Transfers cannot be safely completed.',
            ]);
        }

        foreach ($validated['items'] as $index => $item) {
            $product = Product::where('is_active', true)->find($item['product_id']);
            if (!$product || (int) $product->category_id !== (int) $item['category_id']) {
                throw ValidationException::withMessages([
                    'items.' . $index . '.product_id' => 'Select a product belonging to the chosen category.',
                ]);
            }
        }

        DB::transaction(function () use ($validated, $fromStoreId, $toStoreId) {
            $activeStoreIds = DB::table('stores')
                ->whereIn('id', [$fromStoreId, $toStoreId])
                ->where('is_active', true)
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            if (count($activeStoreIds) !== 2) {
                throw ValidationException::withMessages([
                    'from_location' => 'Select two active locations.',
                ]);
            }

            $activeCategoryIds = DB::table('categories')
                ->whereIn('id', array_unique(array_column($validated['items'], 'category_id')))
                ->where('is_active', true)
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            $products = Product::whereIn('id', array_column($validated['items'], 'product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $preparedItems = [];

            foreach ($validated['items'] as $index => $item) {
                $product = $products->get($item['product_id']);
                if (!in_array($item['category_id'], $activeCategoryIds)
                    || !$product || !$product->is_active
                    || (int) $product->category_id !== (int) $item['category_id']) {
                    throw ValidationException::withMessages([
                        'items.' . $index . '.product_id' => 'Select an active product belonging to the selected active category.',
                    ]);
                }

                $available = (float) DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->where('store_id', $fromStoreId)
                    ->sum(DB::raw('quantity_in - quantity_out'));
                $quantity = (float) $item['quantity'];
                if ($quantity > $available) {
                    throw ValidationException::withMessages([
                        'items.' . $index . '.quantity' => 'Insufficient stock at the source location. Available: '
                            . number_format($available, 2) . ', requested: ' . number_format($quantity, 2) . '.',
                    ]);
                }

                $destinationBalance = (float) DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->where('store_id', $toStoreId)
                    ->sum(DB::raw('quantity_in - quantity_out'));
                $preparedItems[] = [
                    'product' => $product,
                    'category_id' => $item['category_id'],
                    'quantity' => $quantity,
                    'remarks' => $item['remarks'] ?? null,
                    'source_before' => $available,
                    'destination_before' => $destinationBalance,
                ];
            }

            $transferNumber = 'TRF-' . now()->format('Ymd-His') . '-' . Str::upper(Str::random(4));
            $totalQuantity = array_sum(array_column($preparedItems, 'quantity'));
            $firstItem = $preparedItems[0];
            $header = [
                    'transfer_number' => $transferNumber,
                    'product_id' => $firstItem['product']->id,
                    'quantity' => $totalQuantity,
                    'from_location' => trim($validated['from_location']),
                    'to_location' => trim($validated['to_location']),
                    'from_store_id' => $validated['from_store_id'],
                    'to_store_id' => $validated['to_store_id'],
                    'transfer_date' => $validated['transfer_date'],
                    'status' => 'completed',
                    'transfer_type' => 'Internal Stock Transfer',
                    'remarks' => $validated['remarks'] ?? null,
                    'transfer_reason' => $validated['transfer_reason'],
                    'reference_no' => $validated['reference_no'] ?? null,
                    'created_by' => auth()->id(),
                    'approved_by' => $validated['approved_by'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

            if (Schema::hasColumn('stock_transfers', 'submission_key') && !empty($validated['submission_key'])) {
                $header['submission_key'] = $validated['submission_key'];
            }

            $header = array_intersect_key($header, array_flip(Schema::getColumnListing('stock_transfers')));
            $transferId = DB::table('stock_transfers')->insertGetId($header);

            foreach ($preparedItems as $index => $item) {
                $product = $item['product'];
                $quantity = $item['quantity'];
                $itemRecord = [
                    'stock_transfer_id' => $transferId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'category_id' => $item['category_id'],
                    'remarks' => $item['remarks'],
                    'source_before' => $item['source_before'],
                    'destination_before' => $item['destination_before'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                DB::table('stock_transfer_items')->insert(
                    array_intersect_key($itemRecord, array_flip(Schema::getColumnListing('stock_transfer_items')))
                );

                $sourceAfter = $item['source_before'] - $quantity;
                $destinationAfter = $item['destination_before'] + $quantity;
                $baseMovement = [
                    'product_id' => $product->id,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $transferId,
                    'unit_price' => 0,
                    'transaction_date' => $validated['transfer_date'],
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::table('stock_transactions')->insert(array_intersect_key(array_merge($baseMovement, [
                    'reference_number' => $transferNumber . '-' . ($index + 1) . '-OUT',
                    'store_id' => $fromStoreId,
                    'transaction_type' => 'transfer_out',
                    'quantity_in' => 0,
                    'quantity_out' => $quantity,
                    'balance_quantity' => $sourceAfter,
                    'remarks' => 'Transfer out: ' . $transferNumber,
                ]), array_flip(Schema::getColumnListing('stock_transactions'))));
                DB::table('stock_transactions')->insert(array_intersect_key(array_merge($baseMovement, [
                    'reference_number' => $transferNumber . '-' . ($index + 1) . '-IN',
                    'store_id' => $toStoreId,
                    'transaction_type' => 'transfer_in',
                    'quantity_in' => $quantity,
                    'quantity_out' => 0,
                    'balance_quantity' => $destinationAfter,
                    'remarks' => 'Transfer in: ' . $transferNumber,
                ]), array_flip(Schema::getColumnListing('stock_transactions'))));
            }
        });

        return redirect()
            ->route('stock-transfers.index')
            ->with('success', 'Stock transfer completed. Location stock balances were updated without changing total inventory.');
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
            ->leftJoin('stores', 'stores.id', '=', 'stock_inwards.store_id')
            ->leftJoin('stock_transactions as inward_transactions', function ($join) {
                $join->on('inward_transactions.reference_id', '=', 'stock_inwards.id')
                    ->where('inward_transactions.reference_type', '=', 'stock_inward');
            })
            ->where('stock_inwards.is_active', true)
            ->selectRaw("'Stock Inward' as movement_type, COALESCE(stock_inwards.inward_number, stock_inwards.invoice_number) as reference, products.name as product_name, stores.name as location_name, stock_inwards.quantity as quantity, inward_transactions.balance_quantity as balance_quantity, stock_inwards.total_amount as amount, stock_inwards.inward_date as movement_date")
            ->unionAll(
                DB::table('stock_outwards')
                    ->join('products', 'products.id', '=', 'stock_outwards.product_id')
                    ->where('stock_outwards.is_active', true)
                    ->selectRaw("'Stock Outward' as movement_type, stock_outwards.reference_number as reference, products.name as product_name, NULL as location_name, stock_outwards.quantity as quantity, NULL as balance_quantity, stock_outwards.total_amount as amount, stock_outwards.outward_date as movement_date")
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
                ['label' => 'Location', 'key' => 'location_name'],
                ['label' => 'Location', 'key' => 'location_name'],
                ['label' => 'Quantity', 'key' => 'quantity', 'type' => 'quantity'],
                ['label' => 'Balance', 'key' => 'balance_quantity', 'type' => 'quantity'],
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
        if (Schema::hasTable('stock_transactions')
            && Schema::hasColumn('stock_transactions', 'reference_type')
            && Schema::hasColumn('stock_transactions', 'reference_id')
            && Schema::hasColumn('stock_transactions', 'transaction_type')) {
            $ledgerMovements = DB::table('stock_transactions')
                ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transactions.reference_id')
                ->join('products', 'products.id', '=', 'stock_transactions.product_id')
                ->leftJoin('stores', 'stores.id', '=', 'stock_transactions.store_id')
                ->where('stock_transactions.reference_type', 'stock_transfer')
                ->selectRaw("CASE WHEN stock_transactions.transaction_type = 'transfer_out' THEN 'Transfer Out' ELSE 'Transfer In' END as movement_type, stock_transfers.transfer_number as reference, products.name as product_name, stores.name as location_name, (stock_transactions.quantity_in - stock_transactions.quantity_out) as quantity, stock_transactions.balance_quantity as balance_quantity, 0 as amount, stock_transactions.transaction_date as movement_date");

            if (Schema::hasTable('stock_transfer_items')
                && Schema::hasColumn('stock_transfers', 'from_store_id')
                && Schema::hasColumn('stock_transfers', 'to_store_id')) {
                $legacyMovements = DB::table('stock_transfers')
                    ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
                    ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
                    ->join('stores as from_store', 'from_store.id', '=', 'stock_transfers.from_store_id')
                    ->where('stock_transfers.status', '<>', 'cancelled')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('stock_transactions')
                            ->whereColumn('stock_transactions.reference_id', 'stock_transfers.id')
                            ->where('stock_transactions.reference_type', 'stock_transfer');
                    })
                    ->selectRaw("'Stock Transfer (legacy)' as movement_type, stock_transfers.transfer_number as reference, products.name as product_name, from_store.name as location_name, stock_transfer_items.quantity as quantity, NULL as balance_quantity, 0 as amount, stock_transfers.transfer_date as movement_date");

                return $ledgerMovements->unionAll($legacyMovements);
            }

            return $ledgerMovements;
        }

        if (Schema::hasColumn('stock_transfers', 'product_id')
            && (!Schema::hasTable('stock_transfer_items')
                || !Schema::hasColumn('stock_transfers', 'from_store_id'))) {
            return DB::table('stock_transfers')
                ->join('products', 'products.id', '=', 'stock_transfers.product_id')
                ->where('stock_transfers.is_active', true)
                ->selectRaw("'Stock Transfer' as movement_type, stock_transfers.transfer_number as reference, products.name as product_name, NULL as location_name, stock_transfers.quantity as quantity, NULL as balance_quantity, 0 as amount, stock_transfers.transfer_date as movement_date");
        }

        return DB::table('stock_transfers')
            ->join('stock_transfer_items', 'stock_transfer_items.stock_transfer_id', '=', 'stock_transfers.id')
            ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
            ->where('stock_transfers.status', '<>', 'cancelled')
            ->selectRaw("'Stock Transfer' as movement_type, stock_transfers.transfer_number as reference, products.name as product_name, NULL as location_name, stock_transfer_items.quantity as quantity, NULL as balance_quantity, 0 as amount, stock_transfers.transfer_date as movement_date");
    }

    private function adjustmentMovementQuery()
    {
        if (Schema::hasColumn('stock_adjustments', 'adjustment_number')) {
            return DB::table('stock_adjustments')
                ->join('products', 'products.id', '=', 'stock_adjustments.product_id')
                ->selectRaw("'Stock Adjustment' as movement_type, stock_adjustments.adjustment_number as reference, products.name as product_name, NULL as location_name, CASE WHEN LOWER(stock_adjustments.type) LIKE '%decreas%' OR LOWER(stock_adjustments.type) LIKE '%out%' THEN -stock_adjustments.quantity ELSE stock_adjustments.quantity END as quantity, NULL as balance_quantity, 0 as amount, stock_adjustments.adjustment_date as movement_date");
        }

        return DB::table('stock_adjustments')
            ->join('products', 'products.id', '=', 'stock_adjustments.product_id')
            ->where('stock_adjustments.is_active', true)
            ->selectRaw("'Stock Adjustment' as movement_type, stock_adjustments.reference_number as reference, products.name as product_name, NULL as location_name, stock_adjustments.adjustment_quantity as quantity, NULL as balance_quantity, 0 as amount, stock_adjustments.adjustment_date as movement_date");
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
