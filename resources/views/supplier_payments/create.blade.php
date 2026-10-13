@extends('layouts.app')

@section('title', 'New Payment Request')
@section('topbar-title', 'Payment request')

@section('content')
    <div class="page-heading">
        <div><span class="section-kicker">SUPPLIER PAYMENTS</span><h1>New Payment Request</h1><p>Allocate an amount to one or more approved bills of a supplier. The request must be approved before the payment is recorded.</p></div>
        <a class="button button-light" href="{{ route('supplier-payments.index') }}">Back to payments</a>
    </div>
    @if ($errors->any())
        <div class="form-alert" role="alert"><strong>Please check the form:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <section class="form-card">
        <form method="GET" action="{{ route('supplier-payments.create') }}" class="form-section">
            <div class="form-grid">
                <div class="field"><label for="supplier_pick">Supplier <span class="required-mark">*</span></label>
                    <select class="field-control" id="supplier_pick" name="supplier_id" onchange="this.form.submit()"><option value="">Select supplier with approved bills</option>@foreach ($suppliers as $option)<option value="{{ $option->id }}" {{ optional($supplier)->id === $option->id ? 'selected' : '' }}>{{ $option->name }}</option>@endforeach</select>
                </div>
            </div>
        </form>

        @if (!$suppliers->count())
            @include('components.empty-state', ['icon' => 'icon-tray-out', 'title' => 'Nothing to pay', 'message' => 'No supplier has bills approved for payment.'])
        @elseif ($supplier)
            <form action="{{ route('supplier-payments.store') }}" method="POST" data-submit-once>
                @csrf
                <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">
                <input type="hidden" name="supplier_id" value="{{ $supplier->id }}">
                <div class="form-section">
                    <div class="table-wrap"><table class="data-table listing-table">
                        <thead><tr><th>BILL</th><th>INVOICE</th><th>DUE</th><th>TOTAL</th><th>OUTSTANDING</th><th>AVAILABLE TO REQUEST</th><th>AMOUNT TO PAY (₹)</th></tr></thead>
                        <tbody>
                        @forelse ($bills as $bill)
                            @php($available = \App\Support\Money::fromMinor($bill->available_minor))
                            <tr>
                                <td><a href="{{ route('supplier-bills.show', $bill->id) }}">{{ $bill->bill_number }}</a></td>
                                <td>{{ $bill->invoice_number }}</td>
                                <td>{{ $bill->due_date ? \Carbon\Carbon::parse($bill->due_date)->format('d M Y') : '—' }} @if ($bill->isOverdue()) @include('procurement._status', ['status' => 'overdue']) @endif</td>
                                <td>₹{{ number_format((float) $bill->total_amount, 2) }}</td>
                                <td>₹{{ number_format((float) $bill->outstanding_amount, 2) }}</td>
                                <td>₹{{ number_format((float) $available, 2) }}</td>
                                <td><input class="field-control payment-allocation" type="number" min="0" max="{{ $available }}" step="0.01" name="allocations[{{ $bill->id }}]" value="{{ old('allocations.' . $bill->id, $preselectBillId === $bill->id ? $available : '') }}"></td>
                            </tr>
                        @empty
                            <tr><td colspan="7">All approved bills for this supplier are already fully covered by payments or pending requests.</td></tr>
                        @endforelse
                        </tbody>
                    </table></div>
                    <div class="form-actions"><strong>Total requested: <span id="payment-total">₹0.00</span></strong></div>
                </div>
                <div class="form-section">
                    <div class="form-grid">
                        <div class="field"><label for="payment_method">Payment method <span class="required-mark">*</span></label><select class="field-control" id="payment_method" name="payment_method" required>@foreach (\App\SupplierPayment::METHODS as $value => $label)<option value="{{ $value }}" {{ old('payment_method') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                        <div class="field"><label for="account_reference">Paying account reference</label><input class="field-control" id="account_reference" name="account_reference" type="text" maxlength="100" value="{{ old('account_reference') }}" placeholder="e.g. Current A/C ending 1234"><small class="field-hint">Do not enter passwords, OTPs, PINs or full card numbers.</small></div>
                    </div>
                    <div class="field"><label for="notes">Notes</label><textarea class="field-control" id="notes" name="notes" rows="2">{{ old('notes') }}</textarea></div>
                </div>
                <div class="form-actions"><a class="button button-light" href="{{ route('supplier-payments.index') }}">Cancel</a><button class="button button-primary" type="submit" {{ $bills->isEmpty() ? 'disabled' : '' }}>Submit for Approval</button></div>
            </form>
            <script>
                (function () {
                    function total() {
                        var sum = 0;
                        document.querySelectorAll('.payment-allocation').forEach(function (input) { sum += parseFloat(input.value) || 0; });
                        document.getElementById('payment-total').textContent = '₹' + sum.toFixed(2);
                    }
                    document.querySelectorAll('.payment-allocation').forEach(function (input) { input.addEventListener('input', total); });
                    total();
                }());
            </script>
        @endif
    </section>
    @include('procurement._submit_once')
@endsection
