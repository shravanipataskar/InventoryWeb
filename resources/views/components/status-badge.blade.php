@php
    $statusClass = $status === 'Out of stock' ? 'status-danger' : ($status === 'Low stock' ? 'status-warning' : 'status-success');
@endphp
<span class="status-badge {{ $statusClass }}"><span></span>{{ $status }}</span>
