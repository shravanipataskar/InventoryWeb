<?php

namespace App;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class SupplierBill extends Model
{
    const STATUS_UNDER_VERIFICATION = 'under_verification';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_DISPUTED = 'disputed';
    const STATUS_APPROVED = 'approved_for_payment';
    const STATUS_PARTIALLY_PAID = 'partially_paid';
    const STATUS_PAID = 'paid';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';

    // Statuses whose quantities count toward what has been billed on a PO.
    const ACTIVE_STATUSES = ['under_verification', 'on_hold', 'disputed', 'approved_for_payment', 'partially_paid', 'paid'];
    const EDITABLE_STATUSES = ['under_verification', 'on_hold', 'disputed'];
    const PAYABLE_STATUSES = ['approved_for_payment', 'partially_paid'];

    protected $fillable = [
        'bill_number', 'submission_token', 'supplier_id', 'purchase_order_id',
        'invoice_number', 'duplicate_key', 'invoice_date', 'due_date',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'invoice_total',
        'paid_amount', 'status', 'match_status', 'match_result', 'exception_approved',
        'status_reason', 'attachment_path', 'attachment_name', 'notes',
        'created_by', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'match_result' => 'array',
        'exception_approved' => 'boolean',
    ];

    protected $dates = ['approved_at'];

    public static function duplicateKey($supplierId, $invoiceNumber)
    {
        return $supplierId . '|' . strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $invoiceNumber));
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierBillItem::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function purchasePaymentAllocations()
    {
        return $this->hasMany(PurchasePaymentBillAllocation::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function outstandingMinor()
    {
        return Money::toMinor($this->total_amount) - Money::toMinor($this->paid_amount);
    }

    public function getOutstandingAmountAttribute()
    {
        return Money::fromMinor($this->outstandingMinor());
    }

    public function isOverdue()
    {
        return in_array($this->status, self::PAYABLE_STATUSES, true)
            && $this->due_date
            && $this->due_date < now()->toDateString()
            && $this->outstandingMinor() > 0;
    }

    /**
     * Displayed status: OVERDUE is derived from the due date rather than stored.
     */
    public function getDisplayStatusAttribute()
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', self::PAYABLE_STATUSES)
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString());
    }
}
