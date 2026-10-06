<article class="stat-card {{ $tone ?? '' }}">
    <div class="stat-card-top">
        <span class="stat-label">{{ $label }}</span>
        <span class="stat-icon"><svg><use href="#{{ $icon }}"></use></svg></span>
    </div>
    <div class="stat-value">{{ $value }}</div>
    <div class="stat-footnote">{{ $note }}</div>
</article>
