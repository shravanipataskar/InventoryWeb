@php
    $statusTone = [
        'paid' => 'status-success', 'completed' => 'status-success', 'approved_for_payment' => 'status-success',
        'approved' => 'status-success', 'received' => 'status-success', 'matched' => 'status-success',
        'under_verification' => 'status-warning', 'pending_approval' => 'status-warning', 'partially_paid' => 'status-warning',
        'partially_received' => 'status-warning', 'closed' => 'status-warning',
        'on_hold' => 'status-danger', 'disputed' => 'status-danger', 'overdue' => 'status-danger', 'rejected' => 'status-danger',
        'cancelled' => 'status-danger', 'failed' => 'status-danger', 'reversed' => 'status-danger', 'mismatch' => 'status-danger',
    ][$status] ?? 'status-warning';
@endphp
<span class="status-badge {{ $statusTone }}"><span></span>{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
