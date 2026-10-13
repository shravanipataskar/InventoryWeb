<section class="panel listing-panel">
    <div class="panel-heading"><h2>Audit history</h2><p>Recorded automatically; entries cannot be edited or deleted.</p></div>
    <div class="table-wrap"><table class="data-table listing-table">
        <thead><tr><th>WHEN</th><th>EVENT</th><th>STATUS</th><th>BY</th><th>REASON / DETAILS</th></tr></thead>
        <tbody>
        @forelse ($history as $entry)
            <tr>
                <td>{{ optional($entry->created_at)->format('d M Y H:i') }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $entry->event)) }}</td>
                <td>{{ $entry->from_status ? str_replace('_', ' ', $entry->from_status) . ' → ' : '' }}{{ $entry->to_status ? str_replace('_', ' ', $entry->to_status) : '—' }}</td>
                <td>{{ $entry->user_name ?: '—' }}</td>
                <td>
                    {{ $entry->reason ?: '' }}
                    @if ($entry->meta)
                        <small class="field-hint">@foreach ($entry->meta as $key => $value)@if (!is_array($value) && $value !== null && $value !== ''){{ str_replace('_', ' ', $key) }}: {{ $value }}@if (!$loop->last) · @endif @endif @endforeach</small>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No audit entries yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</section>
