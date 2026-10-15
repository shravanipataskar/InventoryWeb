<div class="dashboard-notification" data-notification>
    <button class="dashboard-notification-button" type="button" aria-label="Notifications{{ $headerReorderCount > 0 ? ', ' . $headerReorderCount . ' reorder alerts' : '' }}" aria-expanded="false" aria-controls="reorder-notification-panel" data-notification-toggle>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
        @if ($headerReorderCount > 0)<span class="dashboard-notification-count">{{ $headerReorderCount > 99 ? '99+' : $headerReorderCount }}</span>@endif
    </button>
    <div class="dashboard-notification-panel" id="reorder-notification-panel" role="region" aria-label="Notifications" hidden>
        <div class="dashboard-notification-heading">
            <strong>Notifications</strong>
            @if ($headerReorderCount > 0)<span>{{ $headerReorderCount }} {{ \Illuminate\Support\Str::plural('alert', $headerReorderCount) }}</span>@endif
        </div>
        @if ($headerReorderProducts->isEmpty())
            <p class="dashboard-notification-empty">You have no current notifications.</p>
        @else
            <ul class="dashboard-notification-list">
                @foreach ($headerReorderProducts as $product)
                    <li class="dashboard-notification-item">
                        <span class="dashboard-notification-indicator {{ $product->current_stock <= 0 ? 'is-out' : '' }}" aria-hidden="true"></span>
                        <span class="dashboard-notification-message">
                            <strong>{{ $product->current_stock <= 0 ? 'Out of stock' : 'Reorder required' }}: {{ $product->name }} ({{ $product->product_code }})</strong>
                            <span>Current stock is {{ number_format((float) $product->current_stock, 2) }} {{ optional($product->unit)->short_name }}, below its minimum stock level of {{ number_format((float) $product->minimum_stock, 2) }} {{ optional($product->unit)->short_name }}. Please arrange replenishment.</span>
                        </span>
                    </li>
                @endforeach
                @if ($headerReorderCount > 2)
                    <li class="dashboard-notification-more">
                        <a href="{{ route('dashboard') }}#dashboard-reorder">More ({{ $headerReorderCount - 2 }}) <span aria-hidden="true">→</span></a>
                    </li>
                @endif
            </ul>
        @endif
        @if (auth()->user()->hasPermission('reports'))
            <a class="dashboard-notification-footer" href="{{ route('reports.index', ['tab' => 'low-reorder']) }}">View reorder report <span aria-hidden="true">→</span></a>
        @endif
    </div>
</div>
<style>
    .dashboard-notification{position:relative;display:flex;align-items:center}
    .dashboard-notification-button{position:relative;display:inline-flex;width:40px;height:40px;align-items:center;justify-content:center;border:1px solid #e3eaf0;border-radius:11px;background:#fff;color:#52677b;cursor:pointer}
    .dashboard-notification-button:hover,.dashboard-notification-button[aria-expanded="true"]{border-color:#b9dfe0;background:#f4fbfb;color:#078c8d}
    .dashboard-notification-button svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.7}
    .dashboard-notification-count{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 4px;border:2px solid #fff;border-radius:10px;background:#e34848;color:#fff;font-size:9px;font-weight:700;line-height:14px;text-align:center}
    .dashboard-notification-panel{position:absolute;z-index:60;top:calc(100% + 10px);right:0;width:min(370px,calc(100vw - 28px));overflow:hidden;border:1px solid #e4ebf1;border-radius:13px;background:#fff;box-shadow:0 14px 38px rgba(24,46,67,.16)}
    .dashboard-notification-panel[hidden]{display:none}
    .dashboard-notification-heading{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-bottom:1px solid #edf1f5}
    .dashboard-notification-heading strong{color:#19385e;font-size:13px}
    .dashboard-notification-heading span{color:#8190a0;font-size:11px}
    .dashboard-notification-list{max-height:400px;margin:0;padding:0;overflow-y:auto;list-style:none}
    .dashboard-notification-item{display:flex;align-items:flex-start;gap:10px;padding:12px 15px;border-bottom:1px solid #f0f3f6}
    .dashboard-notification-more{padding:10px 15px;border-bottom:1px solid #f0f3f6;text-align:right}
    .dashboard-notification-more a{color:#078c8d;font-size:11px;font-weight:700}
    .dashboard-notification-more a span{margin-left:4px}
    .dashboard-notification-indicator{width:8px;height:8px;flex:0 0 8px;border-radius:50%;background:#e6a129}
    .dashboard-notification-indicator.is-out{background:#df5151}
    .dashboard-notification-message{display:block;min-width:0;flex:1}
    .dashboard-notification-message strong,.dashboard-notification-message span{display:block}
    .dashboard-notification-message strong{color:#29445e;font-size:11px;line-height:1.5}
    .dashboard-notification-message span{margin-top:4px;color:#718196;font-size:10px;line-height:1.55}
    .dashboard-notification-empty{margin:0;padding:18px 16px;color:#718196;font-size:11px}
    .dashboard-notification-footer{display:flex;justify-content:space-between;padding:12px 16px;color:#078c8d;font-size:11px;font-weight:700}
    @media(max-width:640px){.dashboard-notification-panel{right:-48px}}
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var container = document.querySelector('[data-notification]');
        var toggle = document.querySelector('[data-notification-toggle]');
        var panel = document.getElementById('reorder-notification-panel');
        if (!container || !toggle || !panel) return;
        toggle.addEventListener('click', function () {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.hidden = expanded;
        });
        document.addEventListener('click', function (event) {
            if (!container.contains(event.target)) {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                panel.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
    });
</script>
