<?php

namespace App\Http\Controllers;

use App\PurchasePayment;
use App\Services\PurchasePaymentAllocationService;
use App\Supplier;
use App\SupplierBill;
use App\SupplierPayment;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentHistoryController extends Controller
{
    public function showPurchasePayment($id, PurchasePaymentAllocationService $paymentAllocations)
    {
        abort_unless(
            auth()->user()->hasPermission('payments', 'view')
                || auth()->user()->hasPermission('bills', 'view'),
            403
        );
        $payment = PurchasePayment::with([
            'purchaseOrder.supplier',
            'purchaseOrder.quotation',
            'enteredBy',
            'billAllocations.bill',
        ])->findOrFail($id);
        abort_unless($payment->status === 'paid', 404);

        $order = $payment->purchaseOrder;
        $totalPaid = Money::fromMinor($paymentAllocations->paidMinorForOrder($order->id));
        $outstanding = Money::fromMinor(max(
            0,
            Money::toMinor($order->grand_total) - Money::toMinor($totalPaid)
        ));
        $purchasePayments = $order->payments()->where('status', 'paid')->orderBy('payment_date')->orderBy('id')->get();
        $supplierPayments = SupplierPayment::with(['supplier', 'allocations.bill', 'requester', 'completer'])
            ->where('status', SupplierPayment::STATUS_COMPLETED)
            ->whereHas('allocations.bill', function ($query) use ($order) {
                $query->where('purchase_order_id', $order->id);
            })
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
        $relatedHistory = collect();
        foreach ($purchasePayments as $relatedPayment) {
            $relatedHistory->push((object) [
                'voucher_number' => 'PP-' . str_pad($relatedPayment->id, 8, '0', STR_PAD_LEFT),
                'payment_date' => $relatedPayment->payment_date,
                'amount' => $relatedPayment->amount,
                'recorded_by' => optional($relatedPayment->enteredBy)->name,
                'detail_url' => route('payment-history.purchase-payments.show', $relatedPayment->id),
            ]);
        }
        foreach ($supplierPayments as $relatedPayment) {
            $relatedHistory->push((object) [
                'voucher_number' => $relatedPayment->payment_number,
                'payment_date' => $relatedPayment->payment_date,
                'amount' => $relatedPayment->amount,
                'recorded_by' => optional($relatedPayment->completer)->name ?: optional($relatedPayment->requester)->name,
                'detail_url' => route('supplier-payments.show', $relatedPayment->id),
            ]);
        }
        $relatedHistory = $relatedHistory->sortBy(function ($relatedPayment) {
            return $relatedPayment->payment_date ?: '';
        })->values();

        return view('payment_history.purchase-payment-show', compact(
            'payment',
            'order',
            'totalPaid',
            'outstanding',
            'relatedHistory'
        ));
    }

    public function index(Request $request)
    {
        abort_unless(
            auth()->user()->hasPermission('payments', 'view')
                || auth()->user()->hasPermission('bills', 'view'),
            403
        );

        $filters = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'paid', 'partially_paid', 'unpaid', 'overdue', 'pending_approval'])],
            'search' => 'nullable|string|max:100',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'page' => 'nullable|integer|min:1',
            'per_page' => ['nullable', Rule::in([10, 20, 50])],
            'export' => ['nullable', Rule::in(['csv'])],
        ]);
        $filter = $filters['filter'] ?? 'all';

        $rows = $this->filteredRows($filters, $filter);
        if (($filters['export'] ?? null) === 'csv') {
            return $this->export($rows);
        }

        $countFilters = $filters;
        unset($countFilters['filter'], $countFilters['page'], $countFilters['export']);
        $statusCounts = $this->statusCounts($countFilters);

        $records = $rows->orderByDesc('filter_date')
            ->orderByDesc('source_id')
            ->paginate((int) ($filters['per_page'] ?? 20))
            ->appends($request->except('page', 'export'));
        $records->getCollection()->transform(function ($row) {
            $billTotal = (float) $row->bill_total;
            $billPaid = (float) $row->bill_paid;
            $row->payment_percentage = $billTotal > 0 ? min(100, $billPaid / $billTotal * 100) : 0;
            $row->display_status = $this->statusLabel($row);

            return $row;
        });
        $suppliers = Supplier::orderBy('name')->get(['id', 'name']);
        $summary = $this->summary();
        $summary['partially_paid'] = $statusCounts['partially_paid'];
        $summary['overdue'] = $statusCounts['overdue'];

        return view('payment_history.index', compact('records', 'suppliers', 'summary', 'filter', 'statusCounts'));
    }

    private function filteredRows(array $filters, $filter)
    {
        $transactions = $this->purchasePaymentRows()->unionAll($this->supplierPaymentRows());
        $transactions->unionAll($this->unpaidBillRows($filter));

        $computedRows = DB::query()->fromSub($transactions, 'history')->select('history.*')
            ->selectRaw('CASE WHEN allocated_bill_count > 0 THEN activity_total ELSE COALESCE(order_total, 0) END AS bill_total')
            ->selectRaw('COALESCE(order_paid_purchase, 0) + COALESCE(order_paid_supplier, 0) AS order_paid')
            ->selectRaw('CASE WHEN allocated_bill_count > 0 THEN activity_paid ELSE COALESCE(order_paid_purchase, 0) + COALESCE(order_paid_supplier, 0) END AS bill_paid')
            ->selectRaw('CASE WHEN allocated_bill_count > 0 THEN activity_outstanding WHEN order_total > COALESCE(order_paid_purchase, 0) + COALESCE(order_paid_supplier, 0) THEN order_total - COALESCE(order_paid_purchase, 0) - COALESCE(order_paid_supplier, 0) ELSE 0 END AS bill_outstanding')
            ->selectRaw('CASE WHEN overdue_bill_id IS NOT NULL THEN 1 ELSE 0 END AS is_overdue');
        $rows = DB::query()->fromSub($computedRows, 'records')->select('records.*');

        if (!empty($filters['supplier_id'])) {
            $rows->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (!empty($filters['from_date'])) {
            $rows->whereDate('filter_date', '>=', $filters['from_date']);
        }
        if (!empty($filters['to_date'])) {
            $rows->whereDate('filter_date', '<=', $filters['to_date']);
        }
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $rows->where(function ($query) use ($search) {
                $query->where('voucher_number', 'like', '%' . $search . '%')
                    ->orWhere('supplier_name', 'like', '%' . $search . '%')
                    ->orWhere('purchase_order_number', 'like', '%' . $search . '%')
                    ->orWhere('bill_number', 'like', '%' . $search . '%');

                $query->orWhereExists(function ($subquery) use ($search) {
                    $subquery->select(DB::raw(1))
                        ->from('payment_allocations as search_pa')
                        ->join('supplier_bills as search_b', 'search_b.id', '=', 'search_pa.supplier_bill_id')
                        ->whereColumn('search_pa.supplier_payment_id', 'records.source_id')
                        ->where('records.source_type', 'supplier_payment')
                        ->where('search_b.bill_number', 'like', '%' . $search . '%');
                })->orWhereExists(function ($subquery) use ($search) {
                    $subquery->select(DB::raw(1))
                        ->from('payment_allocations as search_pa')
                        ->join('supplier_bills as search_b', 'search_b.id', '=', 'search_pa.supplier_bill_id')
                        ->join('purchase_orders as search_po', 'search_po.id', '=', 'search_b.purchase_order_id')
                        ->whereColumn('search_pa.supplier_payment_id', 'records.source_id')
                        ->where('records.source_type', 'supplier_payment')
                        ->where('search_po.po_number', 'like', '%' . $search . '%');
                })->orWhereExists(function ($subquery) use ($search) {
                    $subquery->select(DB::raw(1))
                        ->from('purchase_payment_bill_allocations as search_pba')
                        ->join('supplier_bills as search_b', 'search_b.id', '=', 'search_pba.supplier_bill_id')
                        ->whereColumn('search_pba.purchase_payment_id', 'records.source_id')
                        ->where('records.source_type', 'purchase_payment')
                        ->where('search_b.bill_number', 'like', '%' . $search . '%');
                });

                if (preg_match('/^PP-0*(\d+)$/i', $search, $match)) {
                    $query->orWhere(function ($inner) use ($match) {
                        $inner->where('source_type', 'purchase_payment')
                            ->where('source_id', (int) $match[1]);
                    });
                }
            });
        }

        if ($filter === 'paid') {
            $rows->whereIn('payment_status', ['paid', SupplierPayment::STATUS_COMPLETED])
                ->where('bill_total', '>', 0)
                ->whereRaw('bill_paid >= bill_total');
        } elseif ($filter === 'partially_paid') {
            $rows->whereIn('payment_status', ['paid', SupplierPayment::STATUS_COMPLETED])
                ->where('bill_paid', '>', 0)
                ->where('bill_outstanding', '>', 0)
                ->where('is_overdue', 0);
        } elseif ($filter === 'unpaid') {
            $rows->where('source_type', 'bill')
                ->where('bill_paid', '=', 0)
                ->where('is_overdue', 0);
        } elseif ($filter === 'overdue') {
            $rows->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->whereIn('payment_status', ['paid', SupplierPayment::STATUS_COMPLETED])
                        ->where('is_overdue', 1);
                })->orWhere(function ($inner) {
                    $inner->where('source_type', 'bill')
                        ->where('is_overdue', 1);
                });
            });
        } elseif ($filter === 'pending_approval') {
            $rows->where('source_type', 'supplier_payment')
                ->where('payment_status', SupplierPayment::STATUS_PENDING);
        }

        return $rows;
    }

    private function statusCounts(array $filters)
    {
        $rows = $this->filteredRows($filters, 'all');
        $counts = DB::query()->fromSub($rows, 'counted')
            ->selectRaw('COUNT(*) AS all_count')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN payment_status IN (?, ?) AND bill_total > 0 AND bill_paid >= bill_total THEN 1 ELSE 0 END), 0) AS paid_count',
                ['paid', SupplierPayment::STATUS_COMPLETED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN source_type = ? AND bill_paid = 0 AND is_overdue = 0 THEN 1 ELSE 0 END), 0) AS unpaid_count',
                ['bill']
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN payment_status IN (?, ?) AND bill_paid > 0 AND bill_outstanding > 0 AND is_overdue = 0 THEN 1 ELSE 0 END), 0) AS partially_paid_count',
                ['paid', SupplierPayment::STATUS_COMPLETED]
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN (payment_status IN (?, ?) AND is_overdue = 1) OR (source_type = ? AND is_overdue = 1) THEN 1 ELSE 0 END), 0) AS overdue_count',
                ['paid', SupplierPayment::STATUS_COMPLETED, 'bill']
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN source_type = ? AND payment_status = ? THEN 1 ELSE 0 END), 0) AS pending_approval_count',
                ['supplier_payment', SupplierPayment::STATUS_PENDING]
            )
            ->first();

        return [
            'all' => (int) $counts->all_count,
            'paid' => (int) $counts->paid_count,
            'unpaid' => (int) $counts->unpaid_count,
            'partially_paid' => (int) $counts->partially_paid_count,
            'overdue' => (int) $counts->overdue_count,
            'pending_approval' => (int) $counts->pending_approval_count,
        ];
    }

    private function purchasePaymentRows()
    {
        $allocatedBillTotal = $this->allocatedBillAggregate(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id',
            'COALESCE(SUM(b.total_amount), 0)'
        );
        $allocatedBillPaid = $this->allocatedBillAggregate(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id',
            'COALESCE(SUM(b.paid_amount), 0)'
        );
        $allocatedBillOutstanding = $this->allocatedBillAggregate(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id',
            'COALESCE(SUM(CASE WHEN b.status IN (?, ?) THEN b.total_amount - b.paid_amount ELSE 0 END), 0)',
            SupplierBill::PAYABLE_STATUSES
        );
        $allocatedBillCount = $this->allocatedBillAggregate(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id',
            'COUNT(*)'
        );
        $billNumber = $this->allocatedBillAggregate(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id',
            'MIN(b.bill_number)'
        );
        $overdueBill = $this->overdueAllocatedBill(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id'
        );
        $orderPaidByPurchasePayments = DB::table('purchase_payments as pp2')
            ->selectRaw('COALESCE(SUM(pp2.amount), 0)')
            ->whereColumn('pp2.purchase_order_id', 'po.id')
            ->where('pp2.status', 'paid');
        $orderPaidBySupplierPayments = DB::table('payment_allocations as pa2')
            ->join('supplier_bills as b2', 'b2.id', '=', 'pa2.supplier_bill_id')
            ->join('supplier_payments as sp2', 'sp2.id', '=', 'pa2.supplier_payment_id')
            ->selectRaw('COALESCE(SUM(pa2.amount), 0)')
            ->whereColumn('b2.purchase_order_id', 'po.id')
            ->where('sp2.status', SupplierPayment::STATUS_COMPLETED);
        $billCount = $this->allocatedBillAggregate(
            'purchase_payment_bill_allocations',
            'pba',
            'purchase_payment_id',
            'pp.id',
            'COUNT(*)'
        );
        return DB::table('purchase_payments as pp')
            ->join('purchase_orders as po', 'po.id', '=', 'pp.purchase_order_id')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'pp.entered_by')
            ->where('pp.status', 'paid')
            ->selectRaw("'purchase_payment' AS source_type")
            ->addSelect('pp.id as source_id', 'pp.id as voucher_number', 'pp.payment_date')
            ->selectRaw('COALESCE(pp.payment_date, DATE(pp.created_at)) AS filter_date')
            ->addSelect('s.id as supplier_id', 's.name as supplier_name', 'po.id as purchase_order_id', 'po.po_number as purchase_order_number', 'po.quotation_id')
            ->selectSub($billNumber, 'bill_number')
            ->selectSub($billCount, 'bill_count')
            ->addSelect('pp.amount as payment_amount')
            ->selectSub($allocatedBillTotal, 'activity_total')
            ->selectSub($allocatedBillPaid, 'activity_paid')
            ->selectSub($allocatedBillOutstanding, 'activity_outstanding')
            ->selectSub($allocatedBillCount, 'allocated_bill_count')
            ->addSelect('po.grand_total as order_total')
            ->selectSub($orderPaidByPurchasePayments, 'order_paid_purchase')
            ->selectSub($orderPaidBySupplierPayments, 'order_paid_supplier')
            ->selectSub($overdueBill, 'overdue_bill_id')
            ->addSelect(DB::raw("'paid' AS payment_status"), 'u.name as recorded_by_name')
            ->addSelect('pp.payment_method', 'pp.reference_number')
            ->selectRaw('NULL AS account_reference, 0 AS proof_available');
    }

    private function supplierPaymentRows()
    {
        $billTotal = $this->allocatedBillAggregate(
            'payment_allocations',
            'pa',
            'supplier_payment_id',
            'sp.id',
            'COALESCE(SUM(b.total_amount), 0)'
        );
        $billPaid = $this->allocatedBillAggregate(
            'payment_allocations',
            'pa',
            'supplier_payment_id',
            'sp.id',
            'COALESCE(SUM(b.paid_amount), 0)'
        );
        $billOutstanding = $this->allocatedBillAggregate(
            'payment_allocations',
            'pa',
            'supplier_payment_id',
            'sp.id',
            'COALESCE(SUM(CASE WHEN b.status IN (?, ?) THEN b.total_amount - b.paid_amount ELSE 0 END), 0)',
            SupplierBill::PAYABLE_STATUSES
        );
        $billCount = $this->allocatedBillAggregate(
            'payment_allocations',
            'pa',
            'supplier_payment_id',
            'sp.id',
            'COUNT(*)'
        );
        $billNumber = $this->allocatedBillAggregate(
            'payment_allocations',
            'pa',
            'supplier_payment_id',
            'sp.id',
            'MIN(b.bill_number)'
        );
        $purchaseOrderId = DB::table('payment_allocations as pa2')
            ->join('supplier_bills as b2', 'b2.id', '=', 'pa2.supplier_bill_id')
            ->selectRaw('MIN(b2.purchase_order_id)')
            ->whereColumn('pa2.supplier_payment_id', 'sp.id');
        $purchaseOrderNumber = DB::table('payment_allocations as pa2')
            ->join('supplier_bills as b2', 'b2.id', '=', 'pa2.supplier_bill_id')
            ->join('purchase_orders as po2', 'po2.id', '=', 'b2.purchase_order_id')
            ->selectRaw('MIN(po2.po_number)')
            ->whereColumn('pa2.supplier_payment_id', 'sp.id');
        $quotationId = DB::table('payment_allocations as pa2')
            ->join('supplier_bills as b2', 'b2.id', '=', 'pa2.supplier_bill_id')
            ->join('purchase_orders as po2', 'po2.id', '=', 'b2.purchase_order_id')
            ->selectRaw('MIN(po2.quotation_id)')
            ->whereColumn('pa2.supplier_payment_id', 'sp.id');
        $overdueBill = $this->overdueAllocatedBill(
            'payment_allocations',
            'pa',
            'supplier_payment_id',
            'sp.id'
        );

        return DB::table('supplier_payments as sp')
            ->join('suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->leftJoin('users as u', 'u.id', '=', 'sp.requested_by')
            ->leftJoin('users as completed_user', 'completed_user.id', '=', 'sp.completed_by')
            ->selectRaw("'supplier_payment' AS source_type")
            ->addSelect('sp.id as source_id', 'sp.payment_number as voucher_number', 'sp.payment_date')
            ->selectRaw('COALESCE(sp.payment_date, DATE(sp.created_at)) AS filter_date')
            ->addSelect('s.id as supplier_id', 's.name as supplier_name')
            ->selectSub($purchaseOrderId, 'purchase_order_id')
            ->selectSub($purchaseOrderNumber, 'purchase_order_number')
            ->selectSub($quotationId, 'quotation_id')
            ->selectSub($billNumber, 'bill_number')
            ->selectSub($billCount, 'bill_count')
            ->addSelect('sp.amount as payment_amount')
            ->selectSub($billTotal, 'activity_total')
            ->selectSub($billPaid, 'activity_paid')
            ->selectSub($billOutstanding, 'activity_outstanding')
            ->selectSub($billCount, 'allocated_bill_count')
            ->selectRaw('0 AS order_total, 0 AS order_paid_purchase, 0 AS order_paid_supplier')
            ->selectSub($overdueBill, 'overdue_bill_id')
            ->addSelect('sp.status as payment_status')
            ->selectRaw('COALESCE(completed_user.name, u.name) AS recorded_by_name')
            ->addSelect('sp.payment_method', 'sp.reference_number', 'sp.account_reference')
            ->selectRaw('CASE WHEN sp.proof_path IS NULL THEN 0 ELSE 1 END AS proof_available');
    }

    private function unpaidBillRows($filter)
    {
        $query = DB::table('supplier_bills as b')
            ->join('suppliers as s', 's.id', '=', 'b.supplier_id')
            ->join('purchase_orders as po', 'po.id', '=', 'b.purchase_order_id')
            ->leftJoin('users as u', 'u.id', '=', 'b.created_by')
            ->whereIn('b.status', SupplierBill::PAYABLE_STATUSES)
            ->whereRaw('b.paid_amount = 0')
            ->whereRaw('b.total_amount > b.paid_amount')
            ->selectRaw("'bill' AS source_type")
            ->addSelect('b.id as source_id')
            ->selectRaw('NULL AS voucher_number, NULL AS payment_date, b.invoice_date AS filter_date')
            ->addSelect('s.id as supplier_id', 's.name as supplier_name', 'po.id as purchase_order_id', 'po.po_number as purchase_order_number', 'po.quotation_id', 'b.bill_number')
            ->selectRaw('1 AS bill_count, 0 AS payment_amount')
            ->addSelect('b.total_amount as activity_total', 'b.paid_amount as activity_paid')
            ->selectRaw('CASE WHEN b.total_amount > b.paid_amount THEN b.total_amount - b.paid_amount ELSE 0 END AS activity_outstanding')
            ->selectRaw('1 AS allocated_bill_count, 0 AS order_total, 0 AS order_paid_purchase, 0 AS order_paid_supplier')
            ->addSelect(DB::raw('CASE WHEN b.due_date IS NOT NULL AND b.due_date < ? THEN b.id ELSE NULL END AS overdue_bill_id'), 'u.name as recorded_by_name')
            ->addBinding(now()->toDateString(), 'select')
            ->selectRaw("'bill' AS payment_status, NULL AS payment_method, NULL AS reference_number, NULL AS account_reference, 0 AS proof_available");

        if ($filter === 'overdue') {
            $query->whereNotNull('b.due_date')->where('b.due_date', '<', now()->toDateString());
        }

        return $query;
    }

    private function allocatedBillAggregate($table, $allocationAlias, $paymentForeignKey, $paymentColumn, $expression, array $bindings = [])
    {
        return DB::table($table . ' as ' . $allocationAlias)
            ->join('supplier_bills as b', 'b.id', '=', $allocationAlias . '.supplier_bill_id')
            ->selectRaw($expression, $bindings)
            ->whereColumn($allocationAlias . '.' . $paymentForeignKey, $paymentColumn);
    }

    private function overdueAllocatedBill($table, $allocationAlias, $paymentForeignKey, $paymentColumn)
    {
        return DB::table($table . ' as ' . $allocationAlias)
            ->join('supplier_bills as b', 'b.id', '=', $allocationAlias . '.supplier_bill_id')
            ->select('b.id')
            ->whereColumn($allocationAlias . '.' . $paymentForeignKey, $paymentColumn)
            ->whereIn('b.status', SupplierBill::PAYABLE_STATUSES)
            ->whereNotNull('b.due_date')
            ->where('b.due_date', '<', now()->toDateString())
            ->whereRaw('b.total_amount > b.paid_amount')
            ->limit(1);
    }

    private function summary()
    {
        $purchasePaid = DB::table('purchase_payments')->where('status', 'paid')->sum('amount');
        $supplierPaid = DB::table('supplier_payments')->where('status', SupplierPayment::STATUS_COMPLETED)->sum('amount');

        $billOutstanding = DB::table('supplier_bills')
            ->whereIn('status', SupplierBill::PAYABLE_STATUSES)
            ->value(DB::raw('COALESCE(SUM(CASE WHEN total_amount > paid_amount THEN total_amount - paid_amount ELSE 0 END), 0)'));

        $orderPurchasePaid = DB::table('purchase_payments')
            ->selectRaw('purchase_order_id, SUM(amount) AS total_paid')
            ->where('status', 'paid')
            ->groupBy('purchase_order_id');
        $orderSupplierPaid = DB::table('payment_allocations as pa')
            ->join('supplier_bills as b', 'b.id', '=', 'pa.supplier_bill_id')
            ->join('supplier_payments as sp', 'sp.id', '=', 'pa.supplier_payment_id')
            ->selectRaw('b.purchase_order_id, SUM(pa.amount) AS total_paid')
            ->where('sp.status', SupplierPayment::STATUS_COMPLETED)
            ->groupBy('b.purchase_order_id');
        $unbilledOrders = DB::table('purchase_orders as po')
            ->leftJoinSub($orderPurchasePaid, 'pp_paid', function ($join) {
                $join->on('pp_paid.purchase_order_id', '=', 'po.id');
            })
            ->leftJoinSub($orderSupplierPaid, 'sp_paid', function ($join) {
                $join->on('sp_paid.purchase_order_id', '=', 'po.id');
            })
            ->where('po.status', '<>', 'cancelled')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('supplier_bills as b')
                    ->whereColumn('b.purchase_order_id', 'po.id');
            })
            ->value(DB::raw('COALESCE(SUM(CASE WHEN po.grand_total > COALESCE(pp_paid.total_paid, 0) + COALESCE(sp_paid.total_paid, 0) THEN po.grand_total - COALESCE(pp_paid.total_paid, 0) - COALESCE(sp_paid.total_paid, 0) ELSE 0 END), 0)'));

        return [
            'paid' => Money::fromMinor(Money::toMinor($purchasePaid) + Money::toMinor($supplierPaid)),
            'outstanding' => Money::fromMinor(Money::toMinor($billOutstanding) + Money::toMinor($unbilledOrders)),
        ];
    }

    private function export(Builder $rows)
    {
        $filename = 'payment-history-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'Payment Voucher No.',
                'Actual Payment Date',
                'Supplier',
                'Purchase Order / Bill No.',
                'Payment Amount',
                'Total Bill Amount',
                'Total Paid',
                'Outstanding',
                'Payment Percentage',
                'Status',
                'Recorded By',
            ]);
            foreach ($rows->orderByDesc('filter_date')->orderByDesc('source_id')->cursor() as $row) {
                $billTotal = (float) $row->bill_total;
                $billPaid = (float) $row->bill_paid;
                $voucher = $row->source_type === 'purchase_payment'
                    ? 'PP-' . str_pad($row->source_id, 8, '0', STR_PAD_LEFT)
                    : ($row->voucher_number ?: '');
                $values = [
                    $voucher,
                    $row->payment_date ?: '',
                    $row->supplier_name,
                    trim(($row->purchase_order_number ?: '') . ($row->bill_number ? ' / ' . $row->bill_number : '')),
                    number_format((float) $row->payment_amount, 2, '.', ''),
                    number_format($billTotal, 2, '.', ''),
                    number_format($billPaid, 2, '.', ''),
                    number_format((float) $row->bill_outstanding, 2, '.', ''),
                    number_format($billTotal > 0 ? min(100, $billPaid / $billTotal * 100) : 0, 2, '.', '') . '%',
                    $this->statusLabel($row),
                    $row->recorded_by_name ?: '',
                ];
                fputcsv($output, array_map([$this, 'safeCsvValue'], $values));
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function safeCsvValue($value)
    {
        $value = (string) $value;

        return preg_match('/^[\s]*[=+\-@]/', $value) ? "'" . $value : $value;
    }

    private function statusLabel($row)
    {
        if ($row->payment_status === SupplierPayment::STATUS_PENDING) {
            return 'Pending Approval';
        }
        if ($row->payment_status !== 'paid' && $row->payment_status !== SupplierPayment::STATUS_COMPLETED && $row->payment_status !== 'bill') {
            return ucfirst(str_replace('_', ' ', $row->payment_status));
        }
        if ((float) $row->bill_outstanding <= 0 && (float) $row->bill_total > 0) {
            return 'Paid';
        }
        if ((int) $row->is_overdue === 1) {
            return 'Overdue';
        }
        if ((float) $row->bill_paid > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid';
    }
}
