@php
    $statusClass = in_array($status, ['Out of stock', 'Inactive'], true)
        ? 'status-danger'
        : (in_array($status, ['Low stock'], true) ? 'status-warning' : 'status-success');
@endphp
<span class="status-badge {{ $statusClass }}"><span></span>{{ $status }}</span>
