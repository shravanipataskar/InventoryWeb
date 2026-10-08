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
        $headerRows = DB::table('opening_stock_headers')
            ->join('stores', 'stores.id', '=', 'opening_stock_headers.store_id')
            ->leftJoin('users', 'users.id', '=', 'opening_stock_headers.created_by')
            ->join('opening_stock_items', 'opening_stock_items.opening_stock_header_id', '=', 'opening_stock_headers.id')
            ->where('opening_stock_headers.status', 'posted')
            ->selectRaw('opening_stock_headers.opening_number as reference_number, stores.name as location_name, COUNT(opening_stock_items.id) as item_count, SUM(opening_stock_items.quantity) as total_quantity, SUM(opening_stock_items.opening_value) as opening_value, opening_stock_headers.opening_date as transaction_date, users.name as created_by_name, opening_stock_headers.created_at')
            ->groupBy('opening_stock_headers.id', 'opening_stock_headers.opening_number', 'stores.name', 'opening_stock_headers.opening_date', 'users.name', 'opening_stock_headers.created_at');

        $legacyReference = Schema::hasColumn('stock_transactions', 'reference_number')
            ? 'stock_transactions.reference_number'
            : 'NULL';
        $legacyRows = DB::table('stock_transactions')
            ->leftJoin('stores', 'stores.id', '=', 'stock_transactions.store_id')
            ->leftJoin('users', 'users.id', '=', 'stock_transactions.created_by')
            ->whereIn('stock_transactions.reference_type', ['opening_stock', 'initial_stock'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('opening_stock_headers')
                    ->whereColumn('opening_stock_headers.id', 'stock_transactions.reference_id');
            })
            ->selectRaw($legacyReference . ' as reference_number, stores.name as location_name, COUNT(stock_transactions.id) as item_count, SUM(stock_transactions.quantity_in) as total_quantity, SUM(stock_transactions.quantity_in * stock_transactions.unit_price) as opening_value, stock_transactions.transaction_date as transaction_date, users.name as created_by_name, MIN(stock_transactions.created_at) as created_at')
            ->groupBy('stores.name', 'stock_transactions.transaction_date', 'users.name');
        if (Schema::hasColumn('stock_transactions', 'reference_number')) {
            $legacyRows->groupBy('stock_transactions.reference_number');
        } else {
            $legacyRows->groupBy('stock_transactions.id');
        }

        $initialStock = DB::query()
            ->fromSub($headerRows->unionAll($legacyRows), 'opening_stock_records')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $legacySummary = DB::query()->fromSub($legacyRows, 'legacy_opening_records');
        $headerSummary = DB::table('opening_stock_headers')
            ->join('opening_stock_items', 'opening_stock_items.opening_stock_header_id', '=', 'opening_stock_headers.id')
            ->where('opening_stock_headers.status', 'posted');
        $recordCount = (clone $headerSummary)->distinct('opening_stock_headers.id')->count('opening_stock_headers.id')
            + (clone $legacySummary)->count();
        $openingValue = (float) (clone $headerSummary)->sum('opening_stock_items.opening_value')
            + (float) (clone $legacySummary)->sum('opening_value');

        return $this->recordsPage(
            'Opening Stock',
            'INVENTORY',
            'Opening Stock is recorded once during initial inventory setup. Use Stock Inward for future receipts.',
            $initialStock,
            [
                ['label' => 'Opening Stock No.', 'key' => 'reference_number'],
                ['label' => 'Location', 'key' => 'location_name'],
                ['label' => 'Products', 'key' => 'item_count'],
                ['label' => 'Total Quantity', 'key' => 'total_quantity', 'type' => 'quantity'],
                ['label' => 'Opening Value', 'key' => 'opening_value', 'type' => 'currency'],
                ['label' => 'Opening Date', 'key' => 'transaction_date', 'type' => 'date'],
                ['label' => 'Entered By', 'key' => 'created_by_name'],
                ['label' => 'Created At', 'key' => 'created_at', 'type' => 'datetime'],
            ],
            [[
                'label' => 'Opening Stock Transactions',
                'value' => $recordCount,
                'icon' => 'icon-box',
                'tone' => 'blue',
            ], [
                'label' => 'Opening Value',
                'value' => '₹' . number_format($openingValue, 2),
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
                'unit_id',
                'track_batch',
                'track_serial',
            ]);

        $locations = DB::table('stores')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $stockBalances = DB::table('stock_transactions')
            ->select('product_id', 'store_id', DB::raw('SUM(quantity_in - quantity_out) as balance'))
            ->groupBy('product_id', 'store_id')
            ->get()
            ->groupBy('product_id');
        $openingProducts = DB::table('stock_transactions')
            ->whereIn('reference_type', ['opening_stock', 'initial_stock'])
            ->select('product_id', 'store_id')
            ->distinct()
            ->get()
            ->groupBy('product_id');
        $productData = $products->map(function ($product) use ($stockBalances, $openingProducts) {
            $balances = [];
            foreach ($stockBalances->get($product->id, collect()) as $balance) {
                $balances[$balance->store_id] = (float) $balance->balance;
            }
            $openingStores = [];
            foreach ($openingProducts->get($product->id, collect()) as $opening) {
                $openingStores[] = (int) $opening->store_id;
            }

            return [
                'id' => (int) $product->id,
                'category_id' => (int) $product->category_id,
                'name' => $product->name,
                'sku' => $product->product_code,
                'unit' => optional($product->unit)->short_name ?: optional($product->unit)->name,
                'purchase_price' => (float) $product->purchase_price,
                'current_stock' => (float) $product->current_stock,
                'track_batch' => (bool) $product->track_batch,
                'track_serial' => (bool) $product->track_serial,
                'balances' => $balances,
                'opening_stores' => $openingStores,
            ];
        })->values();
        $categoryData = $categories->map(function ($category) {
            return ['id' => (int) $category->id, 'name' => $category->name];
        })->values();

        return view('workspace.opening-stock-create', compact(
            'categories',
            'products',
            'locations',
            'productData',
            'categoryData'
        ));
    }

    public function storeOpeningStock(Request $request)
    {
        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id,is_active,1',
            'transaction_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.category_id' => 'required|integer|exists:categories,id,is_active,1',
            'items.*.product_id' => 'required|integer|exists:products,id,is_active,1',
            'items.*.quantity' => ['required', 'numeric', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'min:0.01'],
            'items.*.unit_purchase_cost' => ['required', 'numeric', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/', 'min:0'],
            'items.*.batch_lot' => 'nullable|string|max:191',
            'items.*.serial_numbers' => 'nullable|string|max:10000',
            'items.*.remarks' => 'nullable|string|max:1000',
            'confirm_existing_stock' => 'nullable|boolean',
        ]);

        $posting = DB::transaction(function () use ($validated, $request) {
        $submittedItems = array_values($validated['items']);
        $products = Product::whereIn('id', array_column($submittedItems, 'product_id'))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        $productRows = [];
        $batchKeys = [];
        $submittedSerials = [];
        $persistedSerials = [];

        foreach ($submittedItems as $index => $item) {
            $product = $products->get($item['product_id']);
            if (!$product || !$product->is_active || (int) $product->category_id !== (int) $item['category_id']) {
                throw ValidationException::withMessages([
                    'items.' . $index . '.product_id' => 'Selected product does not belong to the selected category.',
                ]);
            }
            if ((float) $item['quantity'] * (float) $item['unit_purchase_cost'] > 999999999999.99) {
                throw ValidationException::withMessages([
                    'items.' . $index . '.unit_purchase_cost' => 'The calculated opening value exceeds the maximum supported amount.',
                ]);
            }

            $batch = isset($item['batch_lot']) ? trim($item['batch_lot']) : '';
            if ($product->track_batch && $batch === '') {
                throw ValidationException::withMessages([
                    'items.' . $index . '.batch_lot' => 'A batch or lot number is required for this product.',
                ]);
            }
            if (!$product->track_batch && $batch !== '') {
                throw ValidationException::withMessages([
                    'items.' . $index . '.batch_lot' => 'Batch tracking is not enabled for this product.',
                ]);
            }

            $productKey = (string) $product->id;
            if (isset($batchKeys[$productKey])) {
                if (!$product->track_batch || in_array(mb_strtolower($batch), $batchKeys[$productKey], true)) {
                    throw ValidationException::withMessages([
                        'items.' . $index . '.product_id' => 'A product can appear once unless it is batch-tracked with a different batch number.',
                    ]);
                }
            }
            $batchKeys[$productKey][] = mb_strtolower($batch);

            $serialNumbers = $this->openingStockSerialNumbers(isset($item['serial_numbers']) ? $item['serial_numbers'] : '');
            if ($product->track_serial) {
                if (floor((float) $item['quantity']) !== (float) $item['quantity']
                    || count($serialNumbers) !== (int) $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items.' . $index . '.serial_numbers' => 'Enter exactly one serial number for each whole unit quantity.',
                    ]);
                }
                foreach ($serialNumbers as $serialNumber) {
                    $serialKey = $productKey . ':' . mb_strtolower($serialNumber);
                    if (!array_key_exists($productKey, $persistedSerials)) {
                        $persistedSerials[$productKey] = DB::table('opening_stock_serials')
                            ->where('product_id', $product->id)
                            ->pluck('serial_number')
                            ->map(function ($storedSerial) {
                                return mb_strtolower($storedSerial);
                            })
                            ->all();
                    }
                    if (isset($submittedSerials[$serialKey])
                        || in_array(mb_strtolower($serialNumber), $persistedSerials[$productKey], true)) {
                        throw ValidationException::withMessages([
                            'items.' . $index . '.serial_numbers' => 'Serial numbers must be unique for this product and cannot already be in use.',
                        ]);
                    }
                    $submittedSerials[$serialKey] = true;
                }
            } elseif (count($serialNumbers)) {
                throw ValidationException::withMessages([
                    'items.' . $index . '.serial_numbers' => 'Serial tracking is not enabled for this product.',
                ]);
            }

            $item['batch_lot'] = $batch === '' ? null : $batch;
            $item['serial_numbers_parsed'] = $serialNumbers;
            $productRows[$productKey][] = $item;
        }

        $storeId = (int) $validated['store_id'];
        $existingStockNames = [];
        foreach ($products as $product) {
            $openingExists = DB::table('stock_transactions')
                ->where('product_id', $product->id)
                ->where('store_id', $storeId)
                ->whereIn('reference_type', ['opening_stock', 'initial_stock'])
                ->exists() || DB::table('opening_stock_headers')
                ->join('opening_stock_items', 'opening_stock_items.opening_stock_header_id', '=', 'opening_stock_headers.id')
                ->where('opening_stock_headers.store_id', $storeId)
                ->where('opening_stock_headers.status', 'posted')
                ->where('opening_stock_items.product_id', $product->id)
                ->exists();
            if ($openingExists) {
                throw ValidationException::withMessages([
                    'items' => $product->name . ' already has posted Opening Stock at this location. Use Stock Adjustment for corrections.',
                ]);
            }

            $locationBalance = (float) DB::table('stock_transactions')
                ->where('product_id', $product->id)
                ->where('store_id', $storeId)
                ->sum(DB::raw('quantity_in - quantity_out'));
            if (abs($locationBalance) > 0.00001 || abs((float) $product->current_stock) > 0.00001) {
                $existingStockNames[] = $product->name;
            }
        }

        if (count($existingStockNames) && !$request->boolean('confirm_existing_stock')) {
            throw ValidationException::withMessages([
                'confirm_existing_stock' => 'Review the existing stock warning and confirm before posting.',
            ]);
        }

        $referenceNumber = DB::transaction(function () use ($validated, $productRows, $products, $storeId) {
            $now = now();
            $headerId = DB::table('opening_stock_headers')->insertGetId([
                'opening_number' => 'PENDING-' . Str::uuid(),
                'store_id' => $storeId,
                'opening_date' => $validated['transaction_date'],
                'created_by' => Auth::id(),
                'status' => 'posted',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $reference = 'OPN-' . \Carbon\Carbon::parse($validated['transaction_date'])->format('Ymd') . '-' . str_pad($headerId, 4, '0', STR_PAD_LEFT);
            DB::table('opening_stock_headers')
                ->where('id', $headerId)
                ->update(['opening_number' => $reference, 'updated_at' => $now]);

            foreach ($productRows as $productId => $items) {
                $product = $products->get($productId);
                $totalQuantity = 0.0;
                $totalValue = 0.0;

                foreach ($items as $item) {
                    $quantity = (float) $item['quantity'];
                    $unitCost = (float) $item['unit_purchase_cost'];
                    $openingValue = round($quantity * $unitCost, 2);
                    $totalQuantity += $quantity;
                    $totalValue += $openingValue;
                    $itemId = DB::table('opening_stock_items')->insertGetId([
                        'opening_stock_header_id' => $headerId,
                        'product_id' => $product->id,
                        'category_id' => $item['category_id'],
                        'unit_id' => $product->unit_id,
                        'quantity' => $quantity,
                        'unit_purchase_cost' => $unitCost,
                        'opening_value' => $openingValue,
                        'batch_lot' => $item['batch_lot'],
                        'remarks' => isset($item['remarks']) ? $item['remarks'] : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    foreach ($item['serial_numbers_parsed'] as $serialNumber) {
                        DB::table('opening_stock_serials')->insert([
                            'opening_stock_item_id' => $itemId,
                            'product_id' => $product->id,
                            'store_id' => $storeId,
                            'serial_number' => $serialNumber,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }

                $storeBalance = (float) DB::table('stock_transactions')
                    ->where('product_id', $product->id)
                    ->where('store_id', $storeId)
                    ->sum(DB::raw('quantity_in - quantity_out'));
                DB::table('stock_transactions')->insert([
                    'store_id' => $storeId,
                    'product_id' => $product->id,
                    'transaction_type' => 'opening',
                    'reference_type' => 'opening_stock',
                    'reference_id' => $headerId,
                    'quantity_in' => $totalQuantity,
                    'quantity_out' => 0,
                    'balance_quantity' => $storeBalance + $totalQuantity,
                    'unit_price' => $totalQuantity > 0 ? round($totalValue / $totalQuantity, 2) : 0,
                    'transaction_date' => $validated['transaction_date'],
                    'remarks' => 'Opening Stock ' . $reference,
                    'created_by' => Auth::id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $product->opening_stock = (float) $product->opening_stock + $totalQuantity;
                $product->current_stock = (float) $product->current_stock + $totalQuantity;
                $product->save();
            }

            return $reference;
        });

        return [
            'reference_number' => $referenceNumber,
            'existing_stock_names' => $existingStockNames,
        ];
        });

        $response = redirect()
            ->route('opening-stock.index')
            ->with('success', 'Opening Stock ' . $posting['reference_number'] . ' posted successfully. Stock Movement and Current Stock have been updated.');
        if (count($posting['existing_stock_names'])) {
            $response->with('warning', 'Existing stock was present for ' . implode(', ', $posting['existing_stock_names']) . '. The opening quantities were added; no existing stock was overwritten.');
        }

        return $response;
    }

    private function openingStockSerialNumbers($value)
    {
        $serialNumbers = preg_split('/[\r\n,]+/', (string) $value);
        $serialNumbers = array_filter(array_map('trim', $serialNumbers), function ($serialNumber) {
            return $serialNumber !== '';
        });

        return array_values($serialNumbers);
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
        $openingReference = Schema::hasColumn('stock_transactions', 'reference_number')
            ? "COALESCE(opening_stock_headers.opening_number, stock_transactions.reference_number, 'Legacy Opening')"
            : "COALESCE(opening_stock_headers.opening_number, 'Legacy Opening')";
        $openingMovements = DB::table('stock_transactions')
            ->join('products', 'products.id', '=', 'stock_transactions.product_id')
            ->leftJoin('stores', 'stores.id', '=', 'stock_transactions.store_id')
            ->leftJoin('opening_stock_headers', function ($join) {
                $join->on('opening_stock_headers.id', '=', 'stock_transactions.reference_id')
                    ->where('stock_transactions.reference_type', '=', 'opening_stock');
            })
            ->whereIn('stock_transactions.reference_type', ['opening_stock', 'initial_stock'])
            ->selectRaw("'Opening Stock' as movement_type, " . $openingReference . " as reference, products.name as product_name, stores.name as location_name, (stock_transactions.quantity_in - stock_transactions.quantity_out) as quantity, stock_transactions.balance_quantity as balance_quantity, (stock_transactions.quantity_in * stock_transactions.unit_price) as amount, stock_transactions.transaction_date as movement_date");

        $movementRows = $openingMovements->unionAll(DB::table('stock_inwards')
            ->join('products', 'products.id', '=', 'stock_inwards.product_id')
            ->leftJoin('stores', 'stores.id', '=', 'stock_inwards.store_id')
            ->leftJoin('stock_transactions as inward_transactions', function ($join) {
                $join->on('inward_transactions.reference_id', '=', 'stock_inwards.id')
                    ->where('inward_transactions.reference_type', '=', 'stock_inward');
            })
            ->where('stock_inwards.is_active', true)
            ->selectRaw("'Stock Inward' as movement_type, COALESCE(stock_inwards.inward_number, stock_inwards.invoice_number) as reference, products.name as product_name, stores.name as location_name, stock_inwards.quantity as quantity, inward_transactions.balance_quantity as balance_quantity, stock_inwards.total_amount as amount, stock_inwards.inward_date as movement_date"))
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
            'A unified chronological view of opening, received, issued, transferred, and adjusted stock.',
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
        $filters = $this->purchaseReportFilters(request());
        $rows = $this->purchaseReportQuery($filters);
        $aggregate = DB::query()->fromSub(clone $rows, 'purchase_report_rows')
            ->selectRaw('COUNT(DISTINCT transaction_key) as transaction_count, SUM(accepted_quantity) as item_quantity, SUM(total_value) as total_value, SUM(total_tax) as total_tax')
            ->first();
        $supplierSummary = DB::query()->fromSub(clone $rows, 'supplier_purchase_rows')
            ->selectRaw('supplier_id, supplier_name, COUNT(DISTINCT transaction_key) as transaction_count, SUM(accepted_quantity) as item_quantity, SUM(total_value) as total_value, MAX(purchase_date) as last_purchase_date')
            ->groupBy('supplier_id', 'supplier_name')
            ->orderByDesc('total_value')
            ->limit(6)
            ->get();
        $transactions = DB::query()->fromSub(clone $rows, 'purchase_report_rows')
            ->select('purchase_report_rows.*')
            ->orderBy('purchase_date', 'desc')
            ->orderBy('transaction_key')
            ->paginate(20)
            ->appends(request()->query());
        $suppliers = DB::table('suppliers')->orderBy('name')->get(['id', 'name']);
        $products = DB::table('products')->orderBy('name')->get(['id', 'name', 'product_code']);
        $categories = DB::table('categories')->orderBy('name')->get(['id', 'name']);
        $locations = DB::table('stores')->orderBy('name')->get(['id', 'name']);
        $averageCost = (float) $aggregate->item_quantity > 0
            ? (float) $aggregate->total_value / (float) $aggregate->item_quantity
            : null;

        return view('workspace.purchase-reports', compact(
            'filters',
            'transactions',
            'aggregate',
            'averageCost',
            'supplierSummary',
            'suppliers',
            'products',
            'categories',
            'locations'
        ));
    }

    public function exportPurchaseReports(Request $request, $format)
    {
        $filters = $this->purchaseReportFilters($request);
        $query = $this->purchaseReportQuery($filters)
            ->orderBy('purchase_date', 'desc')
            ->orderBy('transaction_key');
        $reportType = $request->input('report_type', 'transactions');
        if (!in_array($reportType, ['summary', 'transactions', 'details', 'suppliers'], true)) {
            throw ValidationException::withMessages(['report_type' => 'Select a supported purchase report export.']);
        }

        if ($format === 'print') {
            $transactions = $query->get();
            return view('workspace.purchase-reports-print', compact('transactions', 'filters'));
        }

        if ($reportType === 'summary') {
            $summary = DB::query()->fromSub(clone $query, 'summary_purchase_rows')
                ->selectRaw('COUNT(DISTINCT transaction_key) as transaction_count, SUM(accepted_quantity) as accepted_quantity, SUM(total_value) as total_value, SUM(total_tax) as total_tax')
                ->first();
            $bySupplier = $this->purchaseReportBreakdown(clone $query, 'supplier_name');
            $byCategory = $this->purchaseReportBreakdown(clone $query, 'category_name');
            $byLocation = $this->purchaseReportBreakdown(clone $query, 'location_name');
            $exportRows = collect([
                ['Summary', 'Value'],
                ['Date From', $filters['date_from']],
                ['Date To', $filters['date_to']],
                ['Supplier Filter', $request->input('supplier_id') ?: 'All'],
                ['Location Filter', $request->input('store_id') ?: 'All'],
                ['Category Filter', $request->input('category_id') ?: 'All'],
                ['Purchase Transactions', $summary->transaction_count],
                ['Accepted Quantity', $summary->accepted_quantity],
                ['Total Purchase Value', $summary->total_value],
                ['Total Tax', $summary->total_tax],
                [],
            ]);
            foreach ([
                ['By Supplier', $bySupplier],
                ['By Category', $byCategory],
                ['By Location', $byLocation],
            ] as list($heading, $breakdown)) {
                $exportRows->push([$heading, 'Transactions', 'Accepted Quantity', 'Purchase Value', 'Total Tax']);
                foreach ($breakdown as $entry) {
                    $exportRows->push([$entry->dimension, $entry->transaction_count, $entry->accepted_quantity, $entry->total_value, $entry->total_tax]);
                }
                $exportRows->push([]);
            }
            $headers = [];
        } elseif ($reportType === 'suppliers') {
            $exportRows = $this->purchaseReportBreakdown(clone $query, 'supplier_name');
            $headers = ['Supplier', 'Purchase Transactions', 'Accepted Quantity', 'Total Purchase Value', 'Total Tax', 'Last Purchase Date'];
        } else {
            $exportRows = $query;
            $headers = [
            'Purchase Date', 'Purchase Order Number', 'GRN Number', 'Invoice Number',
            'Supplier', 'Product', 'SKU', 'Category', 'Location', 'Ordered Quantity',
            'Received Quantity', 'Rejected Quantity', 'Accepted Quantity', 'Unit Cost',
            'Taxable Value', 'CGST', 'SGST', 'Total Tax', 'Total Purchase Value',
            'Status', 'Created By',
            ];
        }
        $filename = 'purchase-' . $reportType . '-' . now()->format('Ymd-His') . ($format === 'excel' ? '.xls' : '.csv');
        $callback = function () use ($exportRows, $headers, $format, $reportType) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            if ($format === 'excel') {
                fwrite($output, '<table><thead><tr>');
                foreach ($headers as $header) {
                    fwrite($output, '<th>' . e($header) . '</th>');
                }
                fwrite($output, '</tr></thead><tbody>');
            } else {
                if (count($headers)) {
                    fputcsv($output, $headers);
                }
            }
            $writeRow = function ($values) use ($output, $format) {
                if ($format === 'excel') {
                    fwrite($output, '<tr>');
                    foreach ($values as $value) {
                        fwrite($output, '<td>' . e($value === null || $value === '' ? '—' : $value) . '</td>');
                    }
                    fwrite($output, '</tr>');
                } else {
                    fputcsv($output, array_map(function ($value) {
                        $value = (string) $value;
                        return preg_match('/^[\s]*[=+\-@]/', $value) ? "'" . $value : $value;
                    }, $values));
                }
            };
            if ($reportType === 'summary') {
                foreach ($exportRows as $row) {
                    $writeRow($row);
                }
            } elseif ($reportType === 'suppliers') {
                foreach ($exportRows as $row) {
                    $writeRow([
                        $row->dimension,
                        $row->transaction_count,
                        $row->accepted_quantity,
                        $row->total_value,
                        $row->total_tax,
                        $row->last_purchase_date,
                    ]);
                }
            } else {
                $exportRows->chunk(500, function ($rows) use ($writeRow) {
                    foreach ($rows as $row) {
                        $writeRow([
                            $row->purchase_date,
                            $row->po_number,
                            $row->grn_number,
                            $row->invoice_number,
                            $row->supplier_name,
                            $row->product_name,
                            $row->product_code,
                            $row->category_name,
                            $row->location_name,
                            $row->ordered_quantity,
                            $row->received_quantity,
                            $row->rejected_quantity,
                            $row->accepted_quantity,
                            $row->unit_cost,
                            $row->taxable_value,
                            $row->cgst_amount,
                            $row->sgst_amount,
                            $row->total_tax,
                            $row->total_value,
                            $row->status,
                            $row->created_by_name,
                        ]);
                    }
                });
            }
            if ($format === 'excel') {
                fwrite($output, '</tbody></table>');
            }
            fclose($output);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => $format === 'excel'
                ? 'application/vnd.ms-excel; charset=UTF-8'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    private function purchaseReportBreakdown($query, $dimension)
    {
        $query->selectRaw(
            $dimension . ' as dimension, COUNT(DISTINCT transaction_key) as transaction_count, '
            . 'SUM(accepted_quantity) as accepted_quantity, SUM(total_value) as total_value, '
            . 'SUM(total_tax) as total_tax, MAX(purchase_date) as last_purchase_date'
        )
            ->groupBy($dimension)
            ->orderByDesc('total_value');

        return $query->get();
    }

    public function showPurchaseReportRecord($kind, $id)
    {
        if ($kind === 'receipt') {
            $record = \App\GoodsReceipt::with([
                'purchaseOrder',
                'supplier',
                'store',
                'receiver',
                'items.product.category',
                'items.product.unit',
                'items.purchaseOrderItem',
                'items.stockInward',
            ])->findOrFail($id);

            return view('workspace.purchase-report-detail', ['kind' => 'receipt', 'record' => $record]);
        }
        if ($kind === 'order') {
            $record = PurchaseOrder::with([
                'supplier',
                'store',
                'items.product.category',
                'items.product.unit',
                'goodsReceipts.items.stockInward',
            ])->findOrFail($id);

            return view('workspace.purchase-report-detail', compact('kind', 'record'));
        }

        $record = StockInward::with(['product.category', 'product.unit', 'supplier', 'store', 'creator'])
            ->whereNull('goods_receipt_item_id')
            ->findOrFail($id);

        return view('workspace.purchase-report-detail', ['kind' => 'inward', 'record' => $record]);
    }

    private function purchaseReportFilters(Request $request)
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'store_id' => 'nullable|integer|exists:stores,id',
            'status' => 'nullable|in:Received,Partial,Pending,Cancelled,Rejected',
            'search' => 'nullable|string|max:120',
        ]);
        $validated['date_from'] = isset($validated['date_from']) ? $validated['date_from'] : now()->startOfMonth()->toDateString();
        $validated['date_to'] = isset($validated['date_to']) ? $validated['date_to'] : now()->toDateString();

        return $validated;
    }

    private function purchaseReportQuery(array $filters)
    {
        $receiptKey = DB::connection()->getDriverName() === 'sqlite'
            ? "'GRN:' || gr.id"
            : "CONCAT('GRN:', gr.id)";
        $orderKey = DB::connection()->getDriverName() === 'sqlite'
            ? "'PO:' || po.id"
            : "CONCAT('PO:', po.id)";
        $inwardKey = DB::connection()->getDriverName() === 'sqlite'
            ? "'INW:' || si.id"
            : "CONCAT('INW:', si.id)";
        $receiptRows = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchase_orders as po', 'po.id', '=', 'gr.purchase_order_id')
            ->join('purchase_order_items as poi', 'poi.id', '=', 'gri.purchase_order_item_id')
            ->join('products as p', 'p.id', '=', 'gri.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'gr.supplier_id')
            ->leftJoin('stores as st', 'st.id', '=', 'gr.store_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'gr.created_by')
            ->leftJoin('stock_inwards as si', 'si.goods_receipt_item_id', '=', 'gri.id')
            ->selectRaw("'receipt' as source_kind, gr.id as source_id, " . $receiptKey . " as transaction_key, gr.received_date as purchase_date, po.id as purchase_order_id, po.po_number, gr.grn_number, gr.invoice_number, gr.supplier_id, s.name as supplier_name, s.phone as supplier_phone, p.id as product_id, p.name as product_name, p.product_code, p.category_id, c.name as category_name, gr.store_id, st.name as location_name, poi.quantity as ordered_quantity, gri.received_quantity, gri.rejected_quantity, gri.accepted_quantity, gri.purchase_rate as unit_cost, COALESCE(si.subtotal, gri.accepted_quantity * gri.purchase_rate) as taxable_value, COALESCE(si.cgst_amount, 0) as cgst_amount, COALESCE(si.sgst_amount, 0) as sgst_amount, COALESCE(si.tax_total, 0) as total_tax, COALESCE(si.grand_total, si.total_amount, gri.accepted_quantity * gri.purchase_rate) as total_value, CASE WHEN LOWER(po.status) = 'cancelled' THEN 'Cancelled' WHEN gri.accepted_quantity = 0 AND gri.rejected_quantity > 0 THEN 'Rejected' WHEN LOWER(po.status) = 'received' THEN 'Received' ELSE 'Partial' END as status, creator.name as created_by_name, CASE WHEN si.id IS NULL THEN NULL ELSE si.id END as inward_id");

        $pendingRows = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('stores as st', 'st.id', '=', 'po.store_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'po.created_by')
            ->whereNotIn(DB::raw('LOWER(po.status)'), ['received', 'cancelled'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('goods_receipts as gr')
                    ->whereColumn('gr.purchase_order_id', 'po.id');
            })
            ->selectRaw("'order' as source_kind, po.id as source_id, " . $orderKey . " as transaction_key, po.po_date as purchase_date, po.id as purchase_order_id, po.po_number, NULL as grn_number, NULL as invoice_number, po.supplier_id, s.name as supplier_name, s.phone as supplier_phone, p.id as product_id, p.name as product_name, p.product_code, p.category_id, c.name as category_name, po.store_id, st.name as location_name, poi.quantity as ordered_quantity, 0 as received_quantity, 0 as rejected_quantity, 0 as accepted_quantity, poi.purchase_rate as unit_cost, (poi.quantity * poi.purchase_rate) as taxable_value, 0 as cgst_amount, 0 as sgst_amount, 0 as total_tax, (poi.quantity * poi.purchase_rate) as total_value, 'Pending' as status, creator.name as created_by_name, NULL as inward_id");

        $cancelledRows = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->join('products as p', 'p.id', '=', 'poi.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('stores as st', 'st.id', '=', 'po.store_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'po.created_by')
            ->whereRaw('LOWER(po.status) = ?', ['cancelled'])
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('goods_receipts as gr')
                    ->whereColumn('gr.purchase_order_id', 'po.id');
            })
            ->selectRaw("'order' as source_kind, po.id as source_id, " . $orderKey . " as transaction_key, po.po_date as purchase_date, po.id as purchase_order_id, po.po_number, NULL as grn_number, NULL as invoice_number, po.supplier_id, s.name as supplier_name, s.phone as supplier_phone, p.id as product_id, p.name as product_name, p.product_code, p.category_id, c.name as category_name, po.store_id, st.name as location_name, poi.quantity as ordered_quantity, 0 as received_quantity, 0 as rejected_quantity, 0 as accepted_quantity, poi.purchase_rate as unit_cost, 0 as taxable_value, 0 as cgst_amount, 0 as sgst_amount, 0 as total_tax, 0 as total_value, 'Cancelled' as status, creator.name as created_by_name, NULL as inward_id");

        $legacyRows = DB::table('stock_inwards as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
            ->leftJoin('suppliers as s', 's.id', '=', 'si.supplier_id')
            ->leftJoin('stores as st', 'st.id', '=', 'si.store_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'si.created_by')
            ->where('si.is_active', true)
            ->whereNull('si.goods_receipt_item_id')
            ->selectRaw("'inward' as source_kind, si.id as source_id, " . $inwardKey . " as transaction_key, si.inward_date as purchase_date, NULL as purchase_order_id, NULL as po_number, NULL as grn_number, si.invoice_number, si.supplier_id, s.name as supplier_name, s.phone as supplier_phone, p.id as product_id, p.name as product_name, p.product_code, p.category_id, c.name as category_name, si.store_id, st.name as location_name, si.quantity as ordered_quantity, COALESCE(si.received_quantity, si.quantity) as received_quantity, COALESCE(si.rejected_quantity, 0) as rejected_quantity, si.quantity as accepted_quantity, si.purchase_price as unit_cost, COALESCE(si.subtotal, si.total_amount) as taxable_value, COALESCE(si.cgst_amount, 0) as cgst_amount, COALESCE(si.sgst_amount, 0) as sgst_amount, COALESCE(si.tax_total, 0) as total_tax, COALESCE(si.grand_total, si.total_amount) as total_value, 'Received' as status, creator.name as created_by_name, si.id as inward_id");

        $reportRows = $receiptRows
            ->unionAll($pendingRows)
            ->unionAll($cancelledRows)
            ->unionAll($legacyRows);
        $query = DB::query()->fromSub($reportRows, 'purchase_report_rows')
            ->whereBetween('purchase_date', [$filters['date_from'], $filters['date_to']]);

        foreach ([
            'supplier_id' => 'supplier_id',
            'product_id' => 'product_id',
            'category_id' => 'category_id',
            'store_id' => 'store_id',
            'status' => 'status',
        ] as $filter => $column) {
            if (!empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('po_number', 'like', $search)
                    ->orWhere('grn_number', 'like', $search)
                    ->orWhere('invoice_number', 'like', $search)
                    ->orWhere('supplier_name', 'like', $search)
                    ->orWhere('product_name', 'like', $search)
                    ->orWhere('product_code', 'like', $search);
            });
        }

        return $query;
    }

    public function issueReports(Request $request)
    {
        $filters = $this->issueReportFilters($request);
        $rows = $this->issueReportQuery($filters);
        $aggregate = DB::query()->fromSub(clone $rows, 'issue_report_rows')
            ->selectRaw("COUNT(DISTINCT NULLIF(TRIM(reference_number), '')) + COALESCE(SUM(CASE WHEN NULLIF(TRIM(reference_number), '') IS NULL THEN 1 ELSE 0 END), 0) as transaction_count")
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(total_value), 0) as total_value')
            ->selectRaw("COUNT(DISTINCT NULLIF(LOWER(TRIM(recipient_name)), '')) as recipient_count")
            ->first();
        $issues = (clone $rows)
            ->orderBy('issue_date', 'desc')
            ->orderBy('issue_id', 'desc')
            ->paginate(10)
            ->appends($filters);

        $issues->getCollection()->transform(function ($issue) {
            $issue->location_name = $this->issueReportLocation($issue);
            return $issue;
        });

        $recipients = DB::table('stock_outwards as so')
            ->leftJoin('customers as customer', 'customer.id', '=', 'so.customer_id')
            ->where('so.is_active', true)
            ->selectRaw("COALESCE(NULLIF(TRIM(so.issued_to), ''), customer.name) as recipient_name")
            ->whereRaw("NULLIF(TRIM(COALESCE(NULLIF(so.issued_to, ''), customer.name)), '') IS NOT NULL")
            ->distinct()
            ->orderBy('recipient_name')
            ->pluck('recipient_name');
        $products = DB::table('products')->orderBy('name')->get(['id', 'name', 'product_code']);
        $categories = DB::table('categories')->orderBy('name')->get(['id', 'name']);
        $locations = DB::table('halls')->orderBy('name')->get(['id', 'name']);

        return view('workspace.issue-reports', compact(
            'filters',
            'issues',
            'aggregate',
            'recipients',
            'products',
            'categories',
            'locations'
        ));
    }

    public function showIssueReport($id)
    {
        $issue = DB::table('stock_outwards as so')
            ->leftJoin('customers as customer', 'customer.id', '=', 'so.customer_id')
            ->where('so.id', $id)
            ->where('so.is_active', true)
            ->selectRaw("so.*, COALESCE(NULLIF(TRIM(so.issued_to), ''), customer.name) as recipient_name")
            ->first();

        abort_unless($issue, 404);

        $query = $this->issueReportQuery([]);
        if (trim((string) $issue->reference_number) !== '') {
            $query->where('so.reference_number', $issue->reference_number);
        } else {
            $query->where('so.id', $issue->id);
        }
        $items = $query->orderBy('issue_id')->get();
        $items->transform(function ($item) {
            $item->location_name = $this->issueReportLocation($item);
            return $item;
        });
        $totalValue = (float) $items->sum('total_value');

        return view('workspace.issue-report-detail', compact('issue', 'items', 'totalValue'));
    }

    public function exportIssueReports(Request $request, $format)
    {
        $filters = $this->issueReportFilters($request);
        $reportType = $request->input('report_type', 'details');
        if (!in_array($reportType, ['current', 'details', 'recipients', 'products'], true)) {
            throw ValidationException::withMessages(['report_type' => 'Select a supported issue report export.']);
        }

        $query = $this->issueReportQuery($filters);
        if ($format === 'print') {
            $issues = $query->orderBy('issue_date', 'desc')->orderBy('issue_id', 'desc')->get();
            $issues->transform(function ($issue) {
                $issue->location_name = $this->issueReportLocation($issue);
                return $issue;
            });
            return view('workspace.issue-reports-print', compact('issues', 'filters'));
        }

        if ($reportType === 'recipients' || $reportType === 'products') {
            $summary = DB::query()->fromSub(clone $query, 'issue_summary_rows');
            if ($reportType === 'recipients') {
                $summary->selectRaw("recipient_name as dimension, COUNT(DISTINCT product_id) as product_count, SUM(quantity) as total_quantity, SUM(total_value) as total_value, MAX(issue_date) as last_issue_date");
                $summary->groupBy('recipient_name');
            } else {
                $summary->selectRaw("product_name as dimension, product_code, category_name, COUNT(DISTINCT recipient_name) as recipient_count, SUM(quantity) as total_quantity, CASE WHEN SUM(quantity) > 0 THEN SUM(total_value) / SUM(quantity) ELSE 0 END as average_unit_cost, SUM(total_value) as total_value, MAX(issue_date) as last_issue_date");
                $summary->groupBy('product_id', 'product_name', 'product_code', 'category_name');
            }
            $summary->selectRaw("COUNT(DISTINCT NULLIF(TRIM(reference_number), '')) + COALESCE(SUM(CASE WHEN NULLIF(TRIM(reference_number), '') IS NULL THEN 1 ELSE 0 END), 0) as transaction_count")
                ->orderBy('dimension');
            $exportRows = $summary->get();
            $headers = $reportType === 'recipients'
                ? ['Recipient', 'Issue Transactions', 'Products Issued', 'Total Quantity', 'Total Issued Value', 'Last Issue Date']
                : ['Product', 'SKU', 'Category', 'Issue Transactions', 'Recipients', 'Total Quantity Issued', 'Average Unit Cost', 'Total Issued Value', 'Last Issue Date'];
        } else {
            $exportRows = $query->orderBy('issue_date', 'desc')->orderBy('issue_id', 'desc');
            $headers = [
                'Issue Date', 'Reference Number', 'Issue Type', 'Product', 'SKU', 'Category',
                'Recipient', 'Department / Project', 'Location', 'Hall', 'Rack', 'Shelf / Bin',
                'Quantity', 'Unit Cost', 'Total Value', 'Issued By', 'Status',
            ];
        }

        $filename = 'issue-' . $reportType . '-' . now()->format('Ymd-His')
            . ($format === 'excel' ? '.xls' : '.csv');
        $callback = function () use ($exportRows, $headers, $format, $reportType) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            if ($format === 'excel') {
                fwrite($output, '<table><thead><tr>');
                foreach ($headers as $header) {
                    fwrite($output, '<th>' . e($header) . '</th>');
                }
                fwrite($output, '</tr></thead><tbody>');
            } else {
                fputcsv($output, $headers);
            }

            $writeRow = function (array $values) use ($output, $format) {
                if ($format === 'excel') {
                    fwrite($output, '<tr>');
                    foreach ($values as $value) {
                        fwrite($output, '<td>' . e($value === null || $value === '' ? '—' : $value) . '</td>');
                    }
                    fwrite($output, '</tr>');
                    return;
                }
                fputcsv($output, array_map(function ($value) {
                    $value = (string) $value;
                    return preg_match('/^[\s]*[=+\-@]/', $value) ? "'" . $value : $value;
                }, $values));
            };

            if ($reportType === 'recipients') {
                $exportRows->each(function ($row) use ($writeRow) {
                    $writeRow([$row->dimension, $row->transaction_count, $row->product_count, $row->total_quantity, $row->total_value, $row->last_issue_date]);
                });
            } elseif ($reportType === 'products') {
                $exportRows->each(function ($row) use ($writeRow) {
                    $writeRow([$row->dimension, $row->product_code, $row->category_name, $row->transaction_count, $row->recipient_count, $row->total_quantity, $row->average_unit_cost, $row->total_value, $row->last_issue_date]);
                });
            } else {
                $exportRows->chunkById(500, function ($rows) use ($writeRow) {
                    foreach ($rows as $row) {
                        $location = $this->issueReportLocation($row);
                        $writeRow([
                            $row->issue_date,
                            $row->reference_number,
                            null,
                            $row->product_name,
                            $row->product_code,
                            $row->category_name,
                            $row->recipient_name,
                            null,
                            $location,
                            $row->hall_name,
                            $row->rack_name,
                            $row->shelf_name,
                            $row->quantity,
                            $row->unit_cost,
                            $row->total_value,
                            null,
                            $row->is_active ? 'Active' : 'Inactive',
                        ]);
                    }
                }, 'so.id', 'issue_id');
            }
            if ($format === 'excel') {
                fwrite($output, '</tbody></table>');
            }
            fclose($output);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => $format === 'excel'
                ? 'application/vnd.ms-excel; charset=UTF-8'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    private function issueReportFilters(Request $request)
    {
        $today = now()->toDateString();
        $validated = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'recipient' => 'nullable|string|max:255',
            'product_id' => 'nullable|integer|exists:products,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'location_id' => 'nullable|integer|exists:halls,id',
            'search' => 'nullable|string|max:100',
            'report_type' => 'nullable|in:current,details,recipients,products',
        ]);

        return [
            'date_from' => $validated['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? $today,
            'recipient' => $validated['recipient'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'search' => trim($validated['search'] ?? ''),
        ];
    }

    private function issueReportQuery(array $filters)
    {
        $query = DB::table('stock_outwards as so')
            ->leftJoin('products as p', 'p.id', '=', 'so.product_id')
            ->leftJoin('categories as category', 'category.id', '=', 'p.category_id')
            ->leftJoin('customers as customer', 'customer.id', '=', 'so.customer_id')
            ->leftJoin('halls as hall', 'hall.id', '=', 'p.hall_id')
            ->leftJoin('racks as rack', 'rack.id', '=', 'p.rack_id')
            ->leftJoin('shelves as shelf', 'shelf.id', '=', 'p.shelf_id')
            ->where('so.is_active', true)
            ->select([
                'so.id as issue_id',
                'so.reference_number',
                'so.outward_date as issue_date',
                'so.quantity',
                'so.is_active',
                'so.product_id',
                'p.name as product_name',
                'p.product_code',
                'p.category_id',
                'p.hall_id',
                'category.name as category_name',
                'hall.name as hall_name',
                'rack.name as rack_name',
                'shelf.name as shelf_name',
            ])
            ->selectRaw("COALESCE(NULLIF(TRIM(so.issued_to), ''), customer.name) as recipient_name")
            ->selectRaw('COALESCE(p.purchase_price, 0) as unit_cost')
            ->selectRaw('ROUND(so.quantity * COALESCE(p.purchase_price, 0), 2) as total_value');

        if (!empty($filters['date_from'])) {
            $query->whereDate('so.outward_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('so.outward_date', '<=', $filters['date_to']);
        }
        if (!empty($filters['recipient'])) {
            $query->whereRaw("COALESCE(NULLIF(TRIM(so.issued_to), ''), customer.name) = ?", [$filters['recipient']]);
        }
        if (!empty($filters['product_id'])) {
            $query->where('so.product_id', $filters['product_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('p.category_id', $filters['category_id']);
        }
        if (!empty($filters['location_id'])) {
            $query->where('p.hall_id', $filters['location_id']);
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('so.reference_number', 'like', $search)
                    ->orWhere('p.name', 'like', $search)
                    ->orWhere('p.product_code', 'like', $search)
                    ->orWhere('so.issued_to', 'like', $search)
                    ->orWhere('customer.name', 'like', $search)
                    ->orWhere('hall.name', 'like', $search)
                    ->orWhere('rack.name', 'like', $search)
                    ->orWhere('shelf.name', 'like', $search);
            });
        }

        return $query;
    }

    private function issueReportLocation($issue)
    {
        return implode(' / ', array_filter([
            $issue->hall_name,
            $issue->rack_name,
            $issue->shelf_name,
        ], function ($value) {
            return trim((string) $value) !== '';
        }));
    }

    public function stockValuation(Request $request)
    {
        $filters = $this->stockValuationFilters($request);
        $query = $this->stockValuationQuery($filters);
        $aggregate = DB::query()->fromSub(clone $query, 'valuation_rows')
            ->selectRaw('COUNT(*) as product_count, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(stock_value), 0) as total_value')
            ->first();
        $averageUnitCost = (float) $aggregate->total_quantity > 0
            ? (float) $aggregate->total_value / (float) $aggregate->total_quantity
            : null;
        $products = (clone $query)->orderBy('product_name')->paginate(10)->appends($filters);
        $categorySummary = DB::query()->fromSub(clone $query, 'category_valuation_rows')
            ->selectRaw('category_id, category_name, COUNT(*) as product_count, SUM(quantity) as total_quantity, SUM(stock_value) as total_value')
            ->groupBy('category_id', 'category_name')
            ->orderByDesc('total_value')
            ->get();
        $locationSummary = DB::query()->fromSub(clone $query, 'location_valuation_rows')
            ->selectRaw('hall_id, hall_name, COUNT(*) as product_count, SUM(quantity) as total_quantity, SUM(stock_value) as total_value')
            ->groupBy('hall_id', 'hall_name')
            ->orderByDesc('total_value')
            ->get();
        $locations = DB::table('halls')->orderBy('name')->get(['id', 'name']);
        $categories = DB::table('categories')->orderBy('name')->get(['id', 'name']);
        $companies = DB::table('companies')->orderBy('name')->get(['id', 'name']);
        $productOptions = DB::table('products')->where('is_active', true)->orderBy('name')->get(['id', 'name', 'product_code']);

        return view('workspace.stock-valuation', compact(
            'filters',
            'products',
            'aggregate',
            'averageUnitCost',
            'categorySummary',
            'locationSummary',
            'locations',
            'categories',
            'companies',
            'productOptions'
        ));
    }

    public function showStockValuation($id)
    {
        $product = $this->stockValuationQuery([
            'include_zero' => true,
            'product_id' => $id,
        ])->first();

        abort_unless($product, 404);

        return view('workspace.stock-valuation-detail', compact('product'));
    }

    public function exportStockValuation(Request $request, $format)
    {
        $filters = $this->stockValuationFilters($request);
        $reportType = $request->input('report_type', 'details');
        if (!in_array($reportType, ['valuation', 'category', 'location', 'details'], true)) {
            throw ValidationException::withMessages(['report_type' => 'Select a supported stock valuation export.']);
        }

        $query = $this->stockValuationQuery($filters);
        if ($reportType === 'category' || $reportType === 'location') {
            $dimension = $reportType === 'category' ? 'category' : 'location';
            $summary = DB::query()->fromSub(clone $query, 'valuation_export_rows');
            if ($dimension === 'category') {
                $summary->selectRaw('category_name as dimension, COUNT(*) as product_count, SUM(quantity) as total_quantity, SUM(stock_value) as total_value')
                    ->groupBy('category_id', 'category_name');
            } else {
                $summary->selectRaw('hall_name as dimension, COUNT(*) as product_count, SUM(quantity) as total_quantity, SUM(stock_value) as total_value')
                    ->groupBy('hall_id', 'hall_name');
            }
            $exportRows = $summary->orderByDesc('total_value')->get();
            $totalValue = (float) DB::query()->fromSub(clone $query, 'valuation_total_rows')
                ->sum('stock_value');
            $headers = [$dimension === 'category' ? 'Category' : 'Location', 'Product Count', 'Quantity', 'Stock Value', 'Percentage'];
            $rows = $exportRows->map(function ($row) use ($totalValue) {
                return [
                    $row->dimension ?: '—',
                    $row->product_count,
                    $row->total_quantity,
                    $row->total_value,
                    $totalValue > 0 ? round((float) $row->total_value / $totalValue * 100, 2) . '%' : '0%',
                ];
            });
        } else {
            $headers = [
                'Product', 'SKU / Code', 'Barcode', 'Category', 'Company / Brand',
                'Assigned Location', 'Hall', 'Rack', 'Shelf / Bin', 'Batch / Lot',
                'Serial Number', 'Quantity', 'Unit', 'Unit Cost', 'Stock Value',
                'Stock Status', 'Last Movement Date', 'Valuation Method',
            ];
            $rows = null;
        }

        if ($format === 'print') {
            $summary = DB::query()->fromSub(clone $query, 'valuation_print_rows')
                ->selectRaw('COUNT(*) as product_count, COALESCE(SUM(quantity), 0) as total_quantity, COALESCE(SUM(stock_value), 0) as total_value')
                ->first();
            $printCallback = function () use ($query, $filters, $summary) {
                echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Stock Valuation</title>';
                echo '<style>body{font:12px Arial,sans-serif;color:#203944;margin:24px}h1{margin:0 0 6px}p{color:#60727a;margin:0 0 12px}.summary{margin:8px 0 18px;color:#60727a}table{width:100%;border-collapse:collapse}th,td{padding:7px;border:1px solid #dce4e7;text-align:left}th{background:#f1f6f7;font-size:10px}td{font-size:10px}.no-print{margin-bottom:16px}@media print{.no-print{display:none}body{margin:0}}</style>';
                echo '</head><body><div class="no-print"><button onclick="window.print()">Print / Save as PDF</button></div>';
                echo '<h1>Stock Valuation</h1><p>Current on-hand quantity valued at saved product purchase cost · As of ' . e($filters['as_of']) . '</p>';
                echo '<div class="summary">' . number_format((int) $summary->product_count) . ' products · '
                    . number_format((float) $summary->total_quantity, 2) . ' units · ₹'
                    . number_format((float) $summary->total_value, 2) . ' · Purchase Cost</div>';
                echo '<table><thead><tr><th>Product</th><th>SKU / Code</th><th>Barcode</th><th>Category</th><th>Company</th><th>Assigned Location</th><th>Quantity</th><th>Unit</th><th>Unit Cost</th><th>Stock Value</th><th>Status</th><th>Last Movement</th></tr></thead><tbody>';
                (clone $query)->orderBy('product_name')->chunkById(500, function ($products) {
                    foreach ($products as $product) {
                        $location = implode(' / ', array_filter([$product->hall_name, $product->rack_name, $product->shelf_name]));
                        echo '<tr><td>' . e($product->product_name ?: '—') . '</td><td>' . e($product->product_code ?: '—')
                            . '</td><td>' . e($product->barcode ?: '—') . '</td><td>' . e($product->category_name ?: '—')
                            . '</td><td>' . e($product->company_name ?: '—') . '</td><td>' . e($location ?: '—')
                            . '</td><td>' . number_format((float) $product->quantity, 2) . '</td><td>'
                            . e($product->unit_short_name ?: ($product->unit_name ?: '—')) . '</td><td>₹'
                            . number_format((float) $product->unit_cost, 2) . '</td><td>₹'
                            . number_format((float) $product->stock_value, 2) . '</td><td>'
                            . e($product->stock_status) . '</td><td>'
                            . e($product->last_movement_date ?: '—') . '</td></tr>';
                    }
                }, 'products.id', 'id');
                echo '</tbody></table></body></html>';
            };

            return response()->stream($printCallback, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $filename = 'stock-valuation-' . $reportType . '-' . now()->format('Ymd-His')
            . ($format === 'excel' ? '.xls' : '.csv');
        $callback = function () use ($rows, $query, $headers, $format, $reportType) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            if ($format === 'excel') {
                fwrite($output, '<table><thead><tr>');
                foreach ($headers as $header) {
                    fwrite($output, '<th>' . e($header) . '</th>');
                }
                fwrite($output, '</tr></thead><tbody>');
            } else {
                fputcsv($output, $headers);
            }
            $writeRow = function (array $values) use ($output, $format) {
                if ($format === 'excel') {
                    fwrite($output, '<tr>');
                    foreach ($values as $value) {
                        fwrite($output, '<td>' . e($value === null || $value === '' ? '—' : $value) . '</td>');
                    }
                    fwrite($output, '</tr>');
                    return;
                }
                fputcsv($output, array_map(function ($value) {
                    $value = (string) $value;
                    return preg_match('/^[\s]*[=+\-@]/', $value) ? "'" . $value : $value;
                }, $values));
            };
            if ($reportType === 'category' || $reportType === 'location') {
                foreach ($rows as $row) {
                    $writeRow($row);
                }
            } else {
                $query->orderBy('product_name')->chunkById(500, function ($products) use ($writeRow) {
                    foreach ($products as $product) {
                        $writeRow([
                            $product->product_name,
                            $product->product_code,
                            $product->barcode,
                            $product->category_name,
                            $product->company_name,
                            $product->location_name,
                            $product->hall_name,
                            $product->rack_name,
                            $product->shelf_name,
                            null,
                            null,
                            $product->quantity,
                            $product->unit_name,
                            $product->unit_cost,
                            $product->stock_value,
                            $product->stock_status,
                            $product->last_movement_date,
                            'Purchase Cost',
                        ]);
                    }
                }, 'products.id', 'id');
            }
            if ($format === 'excel') {
                fwrite($output, '</tbody></table>');
            }
            fclose($output);
        };

        return response()->streamDownload($callback, $filename, [
            'Content-Type' => $format === 'excel'
                ? 'application/vnd.ms-excel; charset=UTF-8'
                : 'text/csv; charset=UTF-8',
        ]);
    }

    private function stockValuationFilters(Request $request)
    {
        $today = now()->toDateString();
        $validated = $request->validate([
            'as_of' => ['nullable', 'date_format:Y-m-d', function ($attribute, $value, $fail) use ($today) {
                if ($value !== $today) {
                    $fail('Historical valuation is unavailable because outward and legacy stock changes are not all recorded in the stock ledger. Select today for a complete current-stock valuation.');
                }
            }],
            'location_id' => 'nullable|integer|exists:halls,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'stock_status' => 'nullable|in:in_stock,low_stock,out_of_stock',
            'include_zero' => 'nullable|in:0,1',
            'search' => 'nullable|string|max:100',
        ]);

        return [
            'as_of' => $validated['as_of'] ?? $today,
            'location_id' => $validated['location_id'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'company_id' => $validated['company_id'] ?? null,
            'product_id' => $validated['product_id'] ?? null,
            'stock_status' => $validated['stock_status'] ?? null,
            'include_zero' => $validated['include_zero'] ?? '0',
            'search' => trim($validated['search'] ?? ''),
        ];
    }

    private function stockValuationQuery(array $filters)
    {
        $lastMovements = DB::table('stock_transactions')
            ->select('product_id')
            ->selectRaw('transaction_date as movement_date');
        foreach ([
            ['stock_inwards', 'inward_date'],
            ['stock_outwards', 'outward_date'],
            ['stock_adjustments', 'adjustment_date'],
        ] as list($table, $dateColumn)) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $dateColumn)) {
                continue;
            }
            $movementQuery = DB::table($table)->select('product_id', $dateColumn . ' as movement_date');
            if (Schema::hasColumn($table, 'is_active')) {
                $movementQuery->where('is_active', true);
            }
            $lastMovements->unionAll($movementQuery);
        }
        if (Schema::hasTable('stock_transfer_items')
            && Schema::hasTable('stock_transfers')
            && Schema::hasColumn('stock_transfers', 'transfer_date')) {
            $transferMovements = DB::table('stock_transfer_items')
                ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
                ->select('stock_transfer_items.product_id', 'stock_transfers.transfer_date as movement_date');
            if (Schema::hasColumn('stock_transfers', 'is_active')) {
                $transferMovements->where('stock_transfers.is_active', true);
            }
            $lastMovements->unionAll($transferMovements);
        }
        $lastMovementByProduct = DB::query()->fromSub($lastMovements, 'product_movements')
            ->select('product_id')
            ->selectRaw('MAX(movement_date) as last_movement_date')
            ->groupBy('product_id');

        $query = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('halls', 'halls.id', '=', 'products.hall_id')
            ->leftJoin('racks', 'racks.id', '=', 'products.rack_id')
            ->leftJoin('shelves', 'shelves.id', '=', 'products.shelf_id')
            ->leftJoinSub($lastMovementByProduct, 'last_movement', function ($join) {
                $join->on('last_movement.product_id', '=', 'products.id');
            })
            ->where('products.is_active', true)
            ->select([
                'products.id',
                'products.name as product_name',
                'products.product_code',
                'products.barcode',
                'products.category_id',
                'categories.name as category_name',
                'products.company_id',
                'companies.name as company_name',
                'products.hall_id',
                'halls.name as hall_name',
                'halls.name as location_name',
                'racks.name as rack_name',
                'shelves.name as shelf_name',
                'units.name as unit_name',
                'units.short_name as unit_short_name',
                'products.current_stock as quantity',
                'products.purchase_price as unit_cost',
                'products.minimum_stock',
                'products.reorder_level',
                'last_movement.last_movement_date',
            ])
            ->selectRaw('(products.current_stock * products.purchase_price) as stock_value')
            ->selectRaw("CASE WHEN products.current_stock <= 0 THEN 'Out of Stock' WHEN products.current_stock <= products.minimum_stock THEN 'Low Stock' ELSE 'In Stock' END as stock_status");

        if (($filters['include_zero'] ?? '0') !== '1') {
            $query->where('products.current_stock', '>', 0);
        }
        if (!empty($filters['location_id'])) {
            $query->where('products.hall_id', $filters['location_id']);
        }
        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }
        if (!empty($filters['company_id'])) {
            $query->where('products.company_id', $filters['company_id']);
        }
        if (!empty($filters['product_id'])) {
            $query->where('products.id', $filters['product_id']);
        }
        if (!empty($filters['stock_status'])) {
            if ($filters['stock_status'] === 'out_of_stock') {
                $query->where('products.current_stock', '<=', 0);
            } elseif ($filters['stock_status'] === 'low_stock') {
                $query->where('products.current_stock', '>', 0)
                    ->whereColumn('products.current_stock', '<=', 'products.minimum_stock');
            } else {
                $query->where('products.current_stock', '>', 0)
                    ->where(function ($statusQuery) {
                        $statusQuery->whereColumn('products.current_stock', '>', 'products.minimum_stock')
                            ->orWhere('products.minimum_stock', '<=', 0);
                    });
            }
        }
        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('products.name', 'like', $search)
                    ->orWhere('products.product_code', 'like', $search)
                    ->orWhere('products.barcode', 'like', $search);
            });
        }

        return $query;
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
