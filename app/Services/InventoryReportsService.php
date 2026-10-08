<?php

namespace App\Services;

use App\Category;
use App\Company;
use App\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryReportsService
{
    public function options()
    {
        return [
            'locations' => DB::table('stores')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'products' => Product::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'product_code', 'barcode', 'category_id', 'company_id']),
        ];
    }

    public function overview(array $filters)
    {
        $products = $this->productRows($filters);
        $quantityExpression = $this->quantityExpression($filters);
        $stockTotals = (clone $products)->select([])->selectRaw(
            'COALESCE(SUM(' . $quantityExpression . '), 0) as quantity_total, ' .
            'COALESCE(SUM((' . $quantityExpression . ') * products.purchase_price), 0) as value_total, ' .
            'SUM(CASE WHEN (' . $quantityExpression . ') > 0 AND (' . $quantityExpression . ') <= COALESCE(NULLIF(products.reorder_level, 0), products.minimum_stock) THEN 1 ELSE 0 END) as low_count, ' .
            'SUM(CASE WHEN (' . $quantityExpression . ') <= 0 THEN 1 ELSE 0 END) as out_count'
        )->first();

        $received = $this->inwardQuery($filters)->sum('stock_inwards.quantity');
        $issued = $this->outwardQuery($filters)->sum('stock_outwards.quantity');
        $categoryRows = DB::query()->fromSub($products, 'report_products')
            ->selectRaw('category_name as name, COUNT(*) as products_count, COALESCE(SUM(report_quantity), 0) as quantity_total, COALESCE(SUM(report_quantity * purchase_price), 0) as value_total')
            ->groupBy('category_name')
            ->orderByDesc('value_total')
            ->limit(6)
            ->get();
        $locationRows = $this->locationRows($filters);
        $reorderRows = $this->lowStockRows($filters)
            ->orderByRaw('CASE WHEN report_quantity <= 0 THEN 0 ELSE 1 END')
            ->orderBy('products.name')
            ->limit(6)
            ->get();
        $recentMovements = $this->movementQuery($filters)->orderByDesc('movement_date')->orderByDesc('movement_id')->limit(8)->get();
        $trend = $this->trend($filters);

        return compact('stockTotals', 'received', 'issued', 'categoryRows', 'locationRows', 'reorderRows', 'recentMovements', 'trend');
    }

    public function paginatedReport($tab, array $filters)
    {
        switch ($tab) {
            case 'stock-movement':
                return $this->movementQuery($filters)
                    ->orderByDesc('movement_date')->orderByDesc('movement_id')->paginate(20);
            case 'stock-valuation':
                return $this->productRows($filters)->orderBy('products.name')->paginate(20);
            case 'low-reorder':
                return $this->lowStockRows($filters)
                    ->orderByRaw('CASE WHEN report_quantity <= 0 THEN 0 ELSE 1 END')
                    ->orderBy('products.name')->paginate(20);
            case 'purchase-inward':
                return $this->inwardQuery($filters)->orderByDesc('stock_inwards.inward_date')
                    ->orderByDesc('stock_inwards.id')->paginate(20);
            case 'outward':
                return $this->outwardQuery($filters)->orderByDesc('stock_outwards.outward_date')
                    ->orderByDesc('stock_outwards.id')->paginate(20);
            case 'transfer':
                return $this->transferQuery($filters)->orderByDesc('stock_transactions.transaction_date')
                    ->orderByDesc('stock_transactions.id')->paginate(20);
            case 'adjustment':
                return $this->adjustmentQuery($filters)->orderByDesc('stock_transactions.transaction_date')
                    ->orderByDesc('stock_transactions.id')->paginate(20);
            default:
                return collect();
        }
    }

    public function exportQuery($type, array $filters)
    {
        switch ($type) {
            case 'stock-details':
            case 'stock-valuation':
            case 'current-report':
            case 'reorder':
                $query = $this->productRows($filters)->orderBy('products.name');
                if ($type === 'reorder') {
                    $query = $this->lowStockRows($filters)->orderBy('products.name');
                }
                return $query;
            case 'stock-movement':
                return $this->movementQuery($filters)->orderByDesc('movement_date')->orderByDesc('movement_id');
            default:
                return collect();
        }
    }

    public function exportHeaders($type)
    {
        if (in_array($type, ['stock-details', 'current-report'], true)) {
            return ['Product Name', 'SKU', 'Barcode', 'Category', 'Company / Brand', 'Unit', 'Location / Store', 'Hall', 'Rack', 'Shelf / Bin', 'On-Hand Quantity', 'Reserved Quantity', 'Available Quantity', 'Minimum Stock', 'Reorder Level', 'Reorder Quantity', 'Unit Cost', 'Stock Value', 'Stock Status', 'Last Movement Date'];
        }
        if ($type === 'reorder') {
            return ['Product Name', 'SKU', 'Category', 'Company / Brand', 'Location', 'On-Hand Quantity', 'Reserved Quantity', 'Available Quantity', 'Minimum Stock', 'Reorder Level', 'Reorder Quantity', 'Unit Cost', 'Estimated Purchase Value', 'Stock Status', 'Suggested Action'];
        }
        if ($type === 'stock-movement') {
            return ['Transaction Date', 'Transaction Type', 'Reference Number', 'Product Name', 'SKU', 'Category', 'Company / Brand', 'Location', 'Hall', 'Rack', 'Shelf / Bin', 'Quantity In', 'Quantity Out', 'Balance Quantity', 'Unit Cost', 'Stock Value', 'Reason', 'Created By'];
        }
        return ['Product', 'SKU', 'Category', 'Company / Brand', 'Location', 'Quantity', 'Unit', 'Unit Cost', 'Stock Value', 'Stock Status'];
    }

    public function exportValues($type, $row)
    {
        if ($type === 'stock-movement') {
            return [
                $row->movement_date, $row->movement_type, $row->reference_number, $row->product_name,
                $row->product_code, $row->category_name, $row->company_name, $row->location_name,
                $row->hall_name, $row->rack_name, $row->shelf_name, $row->quantity_in, $row->quantity_out,
                $row->balance_quantity, $row->unit_cost, ($row->quantity_in + $row->quantity_out) * $row->unit_cost,
                $row->remarks, $row->created_by_name,
            ];
        }

        if ($type === 'reorder') {
            $reorderLevel = (float) $row->reorder_level > 0 ? (float) $row->reorder_level : (float) $row->minimum_stock;
            $reorderQuantity = (float) $row->reorder_quantity > 0
                ? (float) $row->reorder_quantity
                : max(0, $reorderLevel - (float) $row->report_quantity);
            return [
                $row->name, $row->product_code, $row->category_name, $row->company_name, $row->location_name,
                $row->report_quantity, 0, $row->report_quantity, $row->minimum_stock, $reorderLevel,
                $reorderQuantity, $row->purchase_price, $reorderQuantity * $row->purchase_price,
                $this->stockStatus($row), 'Create Purchase Order',
            ];
        }

        if ($type === 'stock-valuation') {
            return [
                $row->name, $row->product_code, $row->category_name, $row->company_name,
                $row->location_name, $row->report_quantity, $row->unit_name, $row->purchase_price,
                $row->report_quantity * $row->purchase_price, $this->stockStatus($row),
            ];
        }

        return [
            $row->name, $row->product_code, $row->barcode, $row->category_name, $row->company_name,
            $row->unit_name, $row->location_name, $row->hall_name, $row->rack_name, $row->shelf_name,
            $row->report_quantity, 0, $row->report_quantity, $row->minimum_stock, $row->reorder_level,
            $row->reorder_quantity, $row->purchase_price, $row->report_quantity * $row->purchase_price,
            $this->stockStatus($row), $this->lastMovementDate($row),
        ];
    }

    private function lastMovementDate($row)
    {
        return collect([
            $row->last_ledger_date,
            $row->last_inward_date,
            $row->last_outward_date,
        ])->filter()->max();
    }

    public function stockStatus($row)
    {
        if ((float) $row->report_quantity <= 0) {
            return 'Out of Stock';
        }

        $reorder = (float) $row->reorder_level > 0 ? (float) $row->reorder_level : (float) $row->minimum_stock;

        return (float) $row->report_quantity <= $reorder ? 'Low Stock' : 'Healthy';
    }

    private function productRows(array $filters)
    {
        $quantityExpression = $this->quantityExpression($filters);
        $query = Product::query()
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('units', 'units.id', '=', 'products.unit_id')
            ->leftJoin('halls', 'halls.id', '=', 'products.hall_id')
            ->leftJoin('racks', 'racks.id', '=', 'products.rack_id')
            ->leftJoin('shelves', 'shelves.id', '=', 'products.shelf_id')
            ->where('products.is_active', true)
            ->select('products.*')
            ->selectRaw($quantityExpression . ' as report_quantity')
            ->selectRaw('(SELECT MAX(st.transaction_date) FROM stock_transactions st WHERE st.product_id = products.id) as last_ledger_date')
            ->selectRaw('(SELECT MAX(si.inward_date) FROM stock_inwards si WHERE si.product_id = products.id AND si.is_active = 1) as last_inward_date')
            ->selectRaw('(SELECT MAX(so.outward_date) FROM stock_outwards so WHERE so.product_id = products.id AND so.is_active = 1) as last_outward_date')
            ->addSelect([
                'categories.name as category_name',
                'companies.name as company_name',
                'units.short_name as unit_name',
                'halls.name as hall_name',
                'racks.name as rack_name',
                'shelves.name as shelf_name',
            ]);
        $locationName = $filters['location_id']
            ? DB::table('stores')->where('id', $filters['location_id'])->value('name')
            : null;
        $query->selectRaw('? as location_name', [$locationName]);

        $this->applyProductFilters($query, $filters);
        $this->applyStockStatusFilter($query, $filters, $quantityExpression);

        return $query;
    }

    private function lowStockRows(array $filters)
    {
        return $this->productRows($filters)->whereRaw(
            $this->quantityExpression($filters) . ' <= COALESCE(NULLIF(products.reorder_level, 0), products.minimum_stock)'
        );
    }

    private function quantityExpression(array $filters)
    {
        if ($filters['location_id'] && Schema::hasTable('stock_transactions')) {
            $asOf = addslashes($filters['as_of_date']);
            return '(SELECT COALESCE(SUM(st.quantity_in - st.quantity_out), 0) FROM stock_transactions st WHERE st.product_id = products.id AND st.store_id = '
                . (int) $filters['location_id'] . " AND st.transaction_date <= '" . $asOf . "')";
        }

        if ($filters['as_of_date'] >= now()->toDateString()) {
            return 'products.current_stock';
        }

        $asOf = addslashes($filters['as_of_date']);
        $inward = Schema::hasTable('stock_inwards')
            ? "(SELECT COALESCE(SUM(si.quantity), 0) FROM stock_inwards si WHERE si.product_id = products.id AND si.is_active = 1 AND si.inward_date > '" . $asOf . "')"
            : '0';
        $outward = Schema::hasTable('stock_outwards')
            ? "(SELECT COALESCE(SUM(so.quantity), 0) FROM stock_outwards so WHERE so.product_id = products.id AND so.is_active = 1 AND so.outward_date > '" . $asOf . "')"
            : '0';
        $opening = Schema::hasTable('stock_transactions')
            ? "(SELECT COALESCE(SUM(st.quantity_in - st.quantity_out), 0) FROM stock_transactions st WHERE st.product_id = products.id AND st.transaction_type IN ('opening', 'opening_stock') AND st.transaction_date > '" . $asOf . "')"
            : '0';
        $adjustment = '0';
        if (Schema::hasTable('stock_adjustments')) {
            if (Schema::hasColumn('stock_adjustments', 'adjustment_quantity')) {
                $adjustment = "(SELECT COALESCE(SUM(sa.adjustment_quantity), 0) FROM stock_adjustments sa WHERE sa.product_id = products.id AND sa.is_active = 1 AND sa.adjustment_date > '" . $asOf . "')";
            } elseif (Schema::hasColumn('stock_adjustments', 'quantity') && Schema::hasColumn('stock_adjustments', 'type')) {
                $adjustment = "(SELECT COALESCE(SUM(CASE WHEN sa.type = 'increase' THEN sa.quantity ELSE -sa.quantity END), 0) FROM stock_adjustments sa WHERE sa.product_id = products.id AND sa.adjustment_date > '" . $asOf . "')";
            }
        }

        return '(products.current_stock - ' . $inward . ' + ' . $outward . ' - ' . $adjustment . ' - ' . $opening . ')';
    }

    private function applyProductFilters($query, array $filters)
    {
        if ($filters['category_id']) {
            $query->where('products.category_id', $filters['category_id']);
        }
        if ($filters['company_id']) {
            $query->where('products.company_id', $filters['company_id']);
        }
        if ($filters['product_id']) {
            $query->where('products.id', $filters['product_id']);
        }
        if ($filters['search']) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('products.name', 'like', $search)
                    ->orWhere('products.product_code', 'like', $search)
                    ->orWhere('products.barcode', 'like', $search);
            });
        }
    }

    private function applyStockStatusFilter($query, array $filters, $quantityExpression = null)
    {
        if ($filters['stock_status'] === 'all') {
            return;
        }
        $quantityExpression = $quantityExpression ?: $this->quantityExpression($filters);
        if ($filters['stock_status'] === 'out') {
            $query->whereRaw($quantityExpression . ' <= 0');
        } elseif ($filters['stock_status'] === 'low') {
            $query->whereRaw($quantityExpression . ' > 0 AND ' . $quantityExpression . ' <= COALESCE(NULLIF(products.reorder_level, 0), products.minimum_stock)');
        } elseif ($filters['stock_status'] === 'healthy') {
            $query->whereRaw($quantityExpression . ' > COALESCE(NULLIF(products.reorder_level, 0), products.minimum_stock)');
        }
    }

    private function inwardQuery(array $filters)
    {
        $query = DB::table('stock_inwards')
            ->join('products', 'products.id', '=', 'stock_inwards.product_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'stock_inwards.supplier_id')
            ->leftJoin('stores', 'stores.id', '=', 'stock_inwards.store_id')
            ->where('stock_inwards.is_active', true)
            ->select('stock_inwards.*', 'products.name as product_name', 'products.product_code', 'products.purchase_price as product_cost', 'suppliers.name as supplier_name', 'stores.name as location_name');
        if ($filters['date_from']) {
            $query->whereDate('stock_inwards.inward_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('stock_inwards.inward_date', '<=', $filters['date_to']);
        }
        $this->applyProductFilters($query, $filters);
        $this->applyStockStatusFilter($query, $filters);
        if ($filters['location_id']) {
            $query->where('stock_inwards.store_id', $filters['location_id']);
        }

        return $query;
    }

    private function outwardQuery(array $filters)
    {
        $query = DB::table('stock_outwards')
            ->join('products', 'products.id', '=', 'stock_outwards.product_id')
            ->where('stock_outwards.is_active', true)
            ->select('stock_outwards.*', 'products.name as product_name', 'products.product_code', 'products.purchase_price as product_cost', DB::raw('NULL as location_name'));
        if ($filters['date_from']) {
            $query->whereDate('stock_outwards.outward_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('stock_outwards.outward_date', '<=', $filters['date_to']);
        }
        $this->applyProductFilters($query, $filters);
        $this->applyStockStatusFilter($query, $filters);
        if ($filters['location_id']) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function locationRows(array $filters)
    {
        if (!Schema::hasTable('stock_transactions')) {
            return collect();
        }

        $query = DB::table('stock_transactions')
            ->join('products', 'products.id', '=', 'stock_transactions.product_id')
            ->join('stores', 'stores.id', '=', 'stock_transactions.store_id')
            ->where('products.is_active', true)
            ->whereDate('stock_transactions.transaction_date', '<=', $filters['as_of_date'])
            ->selectRaw('stores.id, stores.name, COUNT(DISTINCT CASE WHEN stock_transactions.quantity_in - stock_transactions.quantity_out > 0 THEN products.id END) as products_count, SUM(stock_transactions.quantity_in - stock_transactions.quantity_out) as quantity_total, SUM((stock_transactions.quantity_in - stock_transactions.quantity_out) * products.purchase_price) as value_total')
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('quantity_total');
        if ($filters['location_id']) {
            $query->where('stores.id', $filters['location_id']);
        }
        if ($filters['category_id']) {
            $query->where('products.category_id', $filters['category_id']);
        }
        if ($filters['company_id']) {
            $query->where('products.company_id', $filters['company_id']);
        }
        if ($filters['product_id']) {
            $query->where('products.id', $filters['product_id']);
        }
        if ($filters['search']) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('products.name', 'like', $search)
                    ->orWhere('products.product_code', 'like', $search)
                    ->orWhere('products.barcode', 'like', $search);
            });
        }

        return $query->limit(8)->get();
    }

    private function trend(array $filters)
    {
        $selectedRows = $this->movementQuery($filters)
            ->whereBetween('movement_date', [$filters['date_from'], $filters['date_to']])
            ->select('movement_rows.movement_date')
            ->selectRaw('SUM(movement_rows.quantity_in) as quantity_in, SUM(movement_rows.quantity_out) as quantity_out')
            ->groupBy('movement_date')
            ->orderBy('movement_date')
            ->get();
        $balanceFilters = $filters;
        $balanceFilters['movement_type'] = '';
        $balanceRows = $this->movementQuery($balanceFilters)
            ->whereBetween('movement_date', [$filters['date_from'], $filters['date_to']])
            ->select('movement_rows.movement_date')
            ->selectRaw('SUM(movement_rows.quantity_in) as quantity_in, SUM(movement_rows.quantity_out) as quantity_out')
            ->groupBy('movement_date')
            ->orderBy('movement_date')
            ->get()
            ->keyBy('movement_date');
        $openingFilters = array_merge($filters, ['as_of_date' => date('Y-m-d', strtotime($filters['date_from'] . ' -1 day'))]);
        $openingFilters['movement_type'] = '';
        $openingQuantity = $this->quantityExpression($openingFilters);
        $running = (float) $this->productRows($openingFilters)
            ->select([])->selectRaw('COALESCE(SUM(' . $openingQuantity . '), 0) as opening_quantity')
            ->value('opening_quantity');
        $displayStart = new \DateTime($filters['date_to']);
        $displayStart->modify('-13 days');
        $rangeStart = new \DateTime($filters['date_from']);
        if ($displayStart < $rangeStart) {
            $displayStart = $rangeStart;
        }
        $displayStartKey = $displayStart->format('Y-m-d');
        foreach ($balanceRows as $trendRow) {
            if ($trendRow->movement_date < $displayStartKey) {
                $running += (float) $trendRow->quantity_in - (float) $trendRow->quantity_out;
            }
        }
        $selectedByDate = $selectedRows->keyBy('movement_date');
        $days = [];
        $date = $displayStart;
        $end = new \DateTime($filters['date_to']);
        $end->setTime(0, 0);
        while ($date <= $end) {
            $key = $date->format('Y-m-d');
            $balanceRow = $balanceRows->get($key);
            $selectedRow = $selectedByDate->get($key);
            $in = $selectedRow ? (float) $selectedRow->quantity_in : 0;
            $out = $selectedRow ? (float) $selectedRow->quantity_out : 0;
            if ($balanceRow) {
                $running += (float) $balanceRow->quantity_in - (float) $balanceRow->quantity_out;
            }
            $days[] = (object) ['date' => $key, 'quantity_in' => $in, 'quantity_out' => $out, 'balance' => $running];
            $date->modify('+1 day');
        }

        return $days;
    }

    private function movementQuery(array $filters)
    {
        $transactionReference = Schema::hasColumn('stock_transactions', 'reference_number')
            ? 'stock_transactions.reference_number'
            : 'NULL';
        $ledger = DB::table('stock_transactions')
            ->where(function ($query) {
                $query->whereNull('reference_type')->orWhere('reference_type', '<>', 'stock_inward');
            })
            ->selectRaw('stock_transactions.id as movement_id, stock_transactions.transaction_date as movement_date, stock_transactions.transaction_type as movement_type, ' . $transactionReference . ' as reference_number, stock_transactions.product_id, stock_transactions.store_id, stock_transactions.quantity_in, stock_transactions.quantity_out, stock_transactions.balance_quantity, stock_transactions.unit_price as unit_cost, stock_transactions.remarks, stock_transactions.created_by');
        $inward = DB::table('stock_inwards')
            ->leftJoin('stock_transactions as inward_transaction', function ($join) {
                $join->on('inward_transaction.reference_id', '=', 'stock_inwards.id')
                    ->where('inward_transaction.reference_type', '=', 'stock_inward');
            })
            ->where('stock_inwards.is_active', true)
            ->selectRaw('(1000000000 + stock_inwards.id) as movement_id, stock_inwards.inward_date as movement_date, \'purchase_in\' as movement_type, COALESCE(stock_inwards.inward_number, stock_inwards.invoice_number) as reference_number, stock_inwards.product_id, stock_inwards.store_id, stock_inwards.quantity as quantity_in, 0 as quantity_out, inward_transaction.balance_quantity, stock_inwards.purchase_price as unit_cost, stock_inwards.remarks, stock_inwards.created_by');
        $outward = DB::table('stock_outwards')
            ->where('stock_outwards.is_active', true)
            ->selectRaw('(2000000000 + stock_outwards.id) as movement_id, stock_outwards.outward_date as movement_date, \'sales_out\' as movement_type, stock_outwards.reference_number, stock_outwards.product_id, NULL as store_id, 0 as quantity_in, stock_outwards.quantity as quantity_out, NULL as balance_quantity, products.purchase_price as unit_cost, stock_outwards.remarks, NULL as created_by')
            ->join('products', 'products.id', '=', 'stock_outwards.product_id');
        $sources = $ledger->unionAll($inward)->unionAll($outward);

        $query = DB::query()->fromSub($sources, 'movement_rows')
            ->join('products', 'products.id', '=', 'movement_rows.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('companies', 'companies.id', '=', 'products.company_id')
            ->leftJoin('stores', 'stores.id', '=', 'movement_rows.store_id')
            ->leftJoin('halls', 'halls.id', '=', 'products.hall_id')
            ->leftJoin('racks', 'racks.id', '=', 'products.rack_id')
            ->leftJoin('shelves', 'shelves.id', '=', 'products.shelf_id')
            ->leftJoin('users', 'users.id', '=', 'movement_rows.created_by')
            ->where('products.is_active', true)
            ->select('movement_rows.*', 'products.name as product_name', 'products.product_code', 'categories.name as category_name', 'companies.name as company_name', 'stores.name as location_name', 'halls.name as hall_name', 'racks.name as rack_name', 'shelves.name as shelf_name', 'users.name as created_by_name');

        if ($filters['date_from']) {
            $query->whereDate('movement_rows.movement_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('movement_rows.movement_date', '<=', $filters['date_to']);
        }
        $this->applyProductFilters($query, $filters);
        $this->applyStockStatusFilter($query, $filters);
        if ($filters['location_id']) {
            $query->where('movement_rows.store_id', $filters['location_id']);
        }
        if ($filters['movement_type']) {
            $query->where('movement_rows.movement_type', $filters['movement_type']);
        }
        if ($filters['search']) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($sub) use ($search) {
                $sub->where('movement_rows.reference_number', 'like', $search)
                    ->orWhere('products.name', 'like', $search)
                    ->orWhere('products.product_code', 'like', $search)
                    ->orWhere('products.barcode', 'like', $search)
                    ->orWhere('stores.name', 'like', $search);
            });
        }

        return $query;
    }

    private function transferQuery(array $filters)
    {
        $query = DB::table('stock_transactions')
            ->join('stock_transfers', function ($join) {
                $join->on('stock_transfers.id', '=', 'stock_transactions.reference_id')
                    ->where('stock_transactions.reference_type', '=', 'stock_transfer');
            })
            ->join('products', 'products.id', '=', 'stock_transactions.product_id')
            ->leftJoin('stores as source_store', 'source_store.id', '=', 'stock_transactions.store_id')
            ->leftJoin('stores as destination_store', 'destination_store.id', '=', 'stock_transfers.to_store_id')
            ->leftJoin('users', 'users.id', '=', 'stock_transfers.created_by')
            ->where('stock_transactions.transaction_type', 'transfer_out')
            ->where('products.is_active', true)
            ->select('stock_transactions.*', 'stock_transfers.transfer_number', 'stock_transfers.transfer_date', 'stock_transfers.status', 'stock_transfers.to_store_id', 'products.name as product_name', 'products.product_code', 'products.purchase_price', 'source_store.name as source_name', 'destination_store.name as destination_name', 'users.name as requested_by_name');
        if ($filters['date_from']) {
            $query->whereDate('stock_transfers.transfer_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('stock_transfers.transfer_date', '<=', $filters['date_to']);
        }
        if ($filters['location_id']) {
            $query->where(function ($sub) use ($filters) {
                $sub->where('stock_transactions.store_id', $filters['location_id'])
                    ->orWhere('stock_transfers.to_store_id', $filters['location_id']);
            });
        }
        $this->applyProductFilters($query, $filters);
        $this->applyStockStatusFilter($query, $filters);

        return $query;
    }

    private function adjustmentQuery(array $filters)
    {
        $query = DB::table('stock_transactions')
            ->join('products', 'products.id', '=', 'stock_transactions.product_id')
            ->leftJoin('stores', 'stores.id', '=', 'stock_transactions.store_id')
            ->leftJoin('users', 'users.id', '=', 'stock_transactions.created_by')
            ->where('products.is_active', true)
            ->whereIn('stock_transactions.transaction_type', ['adjustment_in', 'adjustment_out'])
            ->select('stock_transactions.*', 'products.name as product_name', 'products.product_code', 'products.purchase_price', 'stores.name as location_name', 'users.name as created_by_name');
        if ($filters['date_from']) {
            $query->whereDate('stock_transactions.transaction_date', '>=', $filters['date_from']);
        }
        if ($filters['date_to']) {
            $query->whereDate('stock_transactions.transaction_date', '<=', $filters['date_to']);
        }
        if ($filters['location_id']) {
            $query->where('stock_transactions.store_id', $filters['location_id']);
        }
        $this->applyProductFilters($query, $filters);
        $this->applyStockStatusFilter($query, $filters);

        return $query;
    }
}
