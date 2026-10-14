<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PurchasePaymentInstallment extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'installment_number',
        'due_date',
        'planned_amount',
        'submission_token',
        'cancelled_at',
    ];

    protected $dates = ['due_date', 'cancelled_at'];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(PurchasePayment::class, 'installment_id');
    }

    public function status()
    {
        if ($this->cancelled_at) {
            return 'Cancelled';
        }

        $paidMinor = $this->payments->where('status', 'paid')->sum(function ($payment) {
            return \App\Support\Money::toMinor($payment->amount);
        });
        $plannedMinor = \App\Support\Money::toMinor($this->planned_amount);
        if ($paidMinor >= $plannedMinor) {
            return 'Paid';
        }

        $today = Carbon::today();
        $dueDate = Carbon::parse($this->due_date)->startOfDay();
        if ($dueDate->lt($today)) {
            return 'Overdue';
        }
        if ($paidMinor > 0) {
            return 'Partially Paid';
        }
        if ($dueDate->eq($today)) {
            return 'Due';
        }

        return 'Scheduled';
    }
}
