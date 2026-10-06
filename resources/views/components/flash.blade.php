@foreach (['success', 'error', 'warning'] as $flashType)
    @if (session()->has($flashType))
        <div class="toast toast-{{ $flashType }}" role="status" data-toast>
            <span class="toast-icon">
                <svg><use href="#{{ $flashType === 'success' ? 'icon-check' : 'icon-alert' }}"></use></svg>
            </span>
            <span>{{ session($flashType) }}</span>
            <button class="toast-close" type="button" aria-label="Dismiss notification" data-toast-close>
                <svg><use href="#icon-close-small"></use></svg>
            </button>
        </div>
    @endif
@endforeach
