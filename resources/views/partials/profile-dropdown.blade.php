@php
    $authUser = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($authUser->name)))
        ->filter()
        ->map(function ($part) { return strtoupper(substr($part, 0, 1)); })
        ->take(2)->implode('');
@endphp

<div class="profile-menu" id="profileMenu">
    <button type="button" class="profile-trigger" aria-expanded="false" aria-controls="profileDropdown" aria-label="Open profile menu">
        <span class="profile-avatar">{{ $initials ?: 'U' }}</span>
        <span class="profile-trigger-name">{{ $authUser->name }}</span>
        <span class="profile-chevron">⌄</span>
    </button>

    <div class="profile-dropdown" id="profileDropdown" role="menu" aria-labelledby="profileMenu">
        <div class="profile-dropdown-header">
            <span class="profile-avatar profile-avatar-large">{{ $initials ?: 'U' }}</span>
            <div>
                <strong>{{ $authUser->name }}</strong>
                <small>{{ $authUser->email }}</small>
                @php
                    $assignedRole = $authUser->roles->first();
                    $profileRole = optional($assignedRole)->label
                        ?: optional($assignedRole)->name
                        ?: $authUser->role;
                @endphp
                <small>{{ $profileRole ? ucwords(strtolower(str_replace('_', ' ', $profileRole))) : 'No role assigned' }}</small>
                <span class="profile-online"><span></span> Online</span>
            </div>
        </div>

        <div class="profile-dropdown-divider"></div>

        <a href="{{ route('profile.show') }}" class="profile-dropdown-item" role="menuitem">
            <span class="profile-dropdown-icon">♙</span> My Profile
        </a>

        <a href="{{ route('password.change') }}" class="profile-dropdown-item" role="menuitem">
            <span class="profile-dropdown-icon">▣</span> Change Password
        </a>

        <div class="profile-dropdown-divider"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="profile-dropdown-item profile-logout" role="menuitem">
                <span class="profile-dropdown-icon">↪</span> Sign Out
            </button>
        </form>
    </div>
</div>

<script>
(function () {
    var menu = document.getElementById('profileMenu');
    if (!menu) return;

    var trigger = menu.querySelector('.profile-trigger');
    var dropdown = menu.querySelector('.profile-dropdown');

    function setOpen(open) {
        dropdown.classList.toggle('show', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        trigger.setAttribute('aria-label', open ? 'Close profile menu' : 'Open profile menu');
    }

    trigger.addEventListener('click', function () {
        setOpen(!dropdown.classList.contains('show'));
    });

    document.addEventListener('click', function (event) {
        if (!menu.contains(event.target)) setOpen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') setOpen(false);
    });
}());
</script>
