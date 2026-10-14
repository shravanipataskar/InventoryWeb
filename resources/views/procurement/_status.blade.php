@php
    $statusLabel = $status === 'received' ? 'Fully Received' : ucfirst(str_replace('_', ' ', $status));
    $statusTone = [
        'paid' => 'status-success', 'completed' => 'status-success', 'approved_for_payment' => 'status-success',
        'approved' => 'status-success', 'released' => 'status-success', 'received' => 'status-success', 'matched' => 'status-success',
        'draft' => 'status-warning', 'ordered' => 'status-warning', 'pending' => 'status-warning', 'under_verification' => 'status-warning', 'pending_approval' => 'status-warning', 'partially_paid' => 'status-warning',
        'partially_received' => 'status-warning', 'closed' => 'status-warning',
        'on_hold' => 'status-danger', 'disputed' => 'status-danger', 'overdue' => 'status-danger', 'rejected' => 'status-danger',
        'cancelled' => 'status-danger', 'failed' => 'status-danger', 'reversed' => 'status-danger', 'mismatch' => 'status-danger',
    ][$status] ?? 'status-warning';
@endphp
<span class="status-badge {{ $statusTone }}"><span></span>{{ $statusLabel }}</span>
