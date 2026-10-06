@php
    $authUser = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($authUser->name)))
        ->filter()
        ->map(function ($part) { return strtoupper(substr($part, 0, 1)); })
        ->take(2)->implode('');
@endphp

<div class="profile-menu" id="profileMenu">
    <button type="button" class="profile-trigger" onclick="toggleProfileMenu()">
        <span class="profile-avatar">{{ $initials ?: 'U' }}</span>
        <span class="profile-trigger-name">{{ $authUser->name }}</span>
        <span class="profile-chevron">⌄</span>
    </button>

    <div class="profile-dropdown" id="profileDropdown">
        <div class="profile-dropdown-header">
            <span class="profile-avatar profile-avatar-large">{{ $initials ?: 'U' }}</span>
            <div>
                <strong>{{ $authUser->name }}</strong>
                <small>{{ $authUser->email }}</small>
                <span class="profile-online"><span></span> Online</span>
            </div>
        </div>

        <div class="profile-dropdown-divider"></div>

        <a href="{{ route('profile.show') }}" class="profile-dropdown-item">
            <span class="profile-dropdown-icon">♙</span> My Profile
        </a>

        <a href="{{ route('password.change') }}" class="profile-dropdown-item">
            <span class="profile-dropdown-icon">▣</span> Change Password
        </a>

        <div class="profile-dropdown-divider"></div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="profile-dropdown-item profile-logout">
                <span class="profile-dropdown-icon">↪</span> Sign Out
            </button>
        </form>
    </div>
</div>

<script>
function toggleProfileMenu() {
    document.getElementById('profileDropdown').classList.toggle('show');
}
document.addEventListener('click', function (event) {
    const menu = document.getElementById('profileMenu');
    if (menu && !menu.contains(event.target)) {
        document.getElementById('profileDropdown').classList.remove('show');
    }
});
</script>
