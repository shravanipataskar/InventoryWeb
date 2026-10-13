<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SupplierPayment extends Model
{
    const STATUS_PENDING = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REVERSED = 'reversed';

    // Requests that still reserve part of a bill's outstanding balance.
    const IN_FLIGHT_STATUSES = ['pending_approval', 'approved'];

    const METHODS = [
        'neft' => 'NEFT',
        'rtgs' => 'RTGS',
        'imps' => 'IMPS',
        'upi' => 'UPI',
        'cheque' => 'Cheque',
        'cash' => 'Cash',
    ];

    protected $fillable = [
        'payment_number', 'submission_token', 'supplier_id', 'amount', 'payment_method',
        'payment_date', 'reference_number', 'account_reference', 'status', 'notes',
        'status_reason', 'proof_path', 'proof_name', 'requested_by', 'approved_by',
        'approved_at', 'completed_by', 'completed_at',
    ];

    protected $dates = ['approved_at', 'completed_at'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function getMethodLabelAttribute()
    {
        return self::METHODS[$this->payment_method] ?? strtoupper((string) $this->payment_method);
    }
}
