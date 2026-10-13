<?php

namespace App\Services;

use App\PurchaseOrder;
use App\SupplierBill;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierBillService
{
    /**
     * Accepted (GRN) and already-billed quantities per PO item, in hundredths.
     */
    public function quantityPositions(PurchaseOrder $order, $excludeBillId = null)
    {
        $itemIds = $order->items->pluck('id')->all();

        $accepted = DB::table('goods_receipt_items')
            ->whereIn('purchase_order_item_id', $itemIds)
            ->groupBy('purchase_order_item_id')
            ->select('purchase_order_item_id', DB::raw('SUM(accepted_quantity) as quantity'))
            ->pluck('quantity', 'purchase_order_item_id');

        $billed = DB::table('supplier_bill_items')
            ->join('supplier_bills', 'supplier_bills.id', '=', 'supplier_bill_items.supplier_bill_id')
            ->whereIn('supplier_bill_items.purchase_order_item_id', $itemIds)
            ->whereIn('supplier_bills.status', SupplierBill::ACTIVE_STATUSES)
            ->when($excludeBillId, function ($query) use ($excludeBillId) {
                $query->where('supplier_bills.id', '<>', $excludeBillId);
            })
            ->groupBy('supplier_bill_items.purchase_order_item_id')
            ->select('supplier_bill_items.purchase_order_item_id', DB::raw('SUM(supplier_bill_items.quantity) as quantity'))
            ->pluck('quantity', 'purchase_order_item_id');

        $positions = [];
        foreach ($order->items as $item) {
            $acceptedMinor = Money::toMinor($accepted[$item->id] ?? 0);
            $billedMinor = Money::toMinor($billed[$item->id] ?? 0);
            $positions[$item->id] = [
                'ordered' => Money::toMinor($item->ordered_quantity),
                'accepted' => $acceptedMinor,
                'billed' => $billedMinor,
                'billable' => max(0, $acceptedMinor - $billedMinor),
            ];
        }

        return $positions;
    }

    /**
     * Build bill lines from submitted input. Amounts are computed the same way quotations
     * compute GST (rate split into CGST/SGST halves) so matching POs reconcile to the paisa.
     */
    public function buildLines(PurchaseOrder $order, array $input)
    {
        $lines = [];
        foreach ($input as $poItemId => $line) {
            $orderItem = $order->items->firstWhere('id', (int) $poItemId);
            if (!$orderItem) {
                throw ValidationException::withMessages(['items' => 'A billed item does not belong to this purchase order.']);
            }

            $quantity = Money::toMinor($line['quantity'] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $rate = Money::toMinor($line['rate']);
            $discountPercent = Money::toMinor($line['discount_percent'] ?? 0);
            $taxPercent = Money::toMinor($line['tax_percent'] ?? 0);

            $basic = Money::multiply($quantity, $rate);
            $discount = Money::percentOf($basic, $discountPercent);
            $taxable = $basic - $discount;
            $cgstPercent = intdiv($taxPercent + 1, 2);
            $sgstPercent = $taxPercent - $cgstPercent;
            $tax = Money::percentOf($taxable, $cgstPercent) + Money::percentOf($taxable, $sgstPercent);

            $lines[] = [
                'order_item' => $orderItem,
                'quantity' => $quantity,
                'rate' => $rate,
                'discount_percent' => $discountPercent,
                'tax_percent' => $taxPercent,
                'basic' => $basic,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $taxable + $tax,
            ];
        }

        if (!$lines) {
            throw ValidationException::withMessages(['items' => 'Enter a billed quantity for at least one item.']);
        }

        return $lines;
    }

    public function totals(array $lines)
    {
        $totals = ['subtotal' => 0, 'discount' => 0, 'tax' => 0, 'total' => 0];
        foreach ($lines as $line) {
            $totals['subtotal'] += $line['basic'];
            $totals['discount'] += $line['discount'];
            $totals['tax'] += $line['tax'];
            $totals['total'] += $line['total'];
        }

        return $totals;
    }

    /**
     * Three-way match: PO (agreed price/tax) x GRN (accepted quantity) x invoice (billed lines and total).
     */
    public function match(PurchaseOrder $order, array $lines, $invoiceTotalMinor, $supplierId, $excludeBillId = null)
    {
        $positions = $this->quantityPositions($order, $excludeBillId);
        $issues = [];

        if ((int) $supplierId !== (int) $order->supplier_id) {
            $issues[] = ['type' => 'supplier', 'message' => 'Bill supplier does not match the purchase order supplier.'];
        }

        foreach ($lines as $line) {
            $item = $line['order_item'];
            $name = optional($item->product)->name ?: 'Item #' . $item->id;
            $position = $positions[$item->id];

            if ($line['rate'] !== Money::toMinor($item->purchase_rate)) {
                $issues[] = ['type' => 'price', 'item_id' => $item->id, 'message' => $name . ': billed rate ₹' . Money::fromMinor($line['rate']) . ' differs from PO rate ₹' . Money::fromMinor(Money::toMinor($item->purchase_rate)) . '.'];
            }
            if ($line['discount_percent'] !== Money::toMinor($item->discount_percent)) {
                $issues[] = ['type' => 'discount', 'item_id' => $item->id, 'message' => $name . ': billed discount ' . Money::fromMinor($line['discount_percent']) . '% differs from PO discount ' . Money::fromMinor(Money::toMinor($item->discount_percent)) . '%.'];
            }
            if ($line['tax_percent'] !== Money::toMinor($item->tax_percent)) {
                $issues[] = ['type' => 'tax', 'item_id' => $item->id, 'message' => $name . ': billed tax ' . Money::fromMinor($line['tax_percent']) . '% differs from PO tax ' . Money::fromMinor(Money::toMinor($item->tax_percent)) . '%.'];
            }
            if ($position['billed'] + $line['quantity'] > $position['accepted']) {
                $issues[] = ['type' => 'quantity', 'item_id' => $item->id, 'message' => $name . ': billed ' . Money::fromMinor($line['quantity']) . ' but only ' . Money::fromMinor($position['billable']) . ' accepted quantity remains unbilled (accepted ' . Money::fromMinor($position['accepted']) . ', already billed ' . Money::fromMinor($position['billed']) . ').'];
            }
        }

        $computedTotal = $this->totals($lines)['total'];
        if ($invoiceTotalMinor !== $computedTotal) {
            $issues[] = ['type' => 'total', 'message' => 'Invoice total ₹' . Money::fromMinor($invoiceTotalMinor) . ' differs from the computed line total ₹' . Money::fromMinor($computedTotal) . '.'];
        }

        return [
            'status' => $issues ? 'mismatch' : 'matched',
            'issues' => $issues,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Persist lines and totals onto a bill (replacing previous lines when correcting).
     */
    public function writeLines(SupplierBill $bill, array $lines)
    {
        $bill->items()->delete();
        foreach ($lines as $line) {
            $bill->items()->create([
                'purchase_order_item_id' => $line['order_item']->id,
                'product_id' => $line['order_item']->product_id,
                'quantity' => Money::fromMinor($line['quantity']),
                'rate' => Money::fromMinor($line['rate']),
                'discount_percent' => Money::fromMinor($line['discount_percent']),
                'tax_percent' => Money::fromMinor($line['tax_percent']),
                'basic_amount' => Money::fromMinor($line['basic']),
                'discount_amount' => Money::fromMinor($line['discount']),
                'tax_amount' => Money::fromMinor($line['tax']),
                'total_amount' => Money::fromMinor($line['total']),
            ]);
        }

        $totals = $this->totals($lines);

        return [
            'subtotal' => Money::fromMinor($totals['subtotal']),
            'discount_amount' => Money::fromMinor($totals['discount']),
            'tax_amount' => Money::fromMinor($totals['tax']),
            'total_amount' => Money::fromMinor($totals['total']),
        ];
    }
}
