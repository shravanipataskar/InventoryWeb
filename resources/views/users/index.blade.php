@extends('layouts.app')

@section('title', 'Users')
@section('topbar-title', 'Users')

@section('content')
<div class="page-heading workspace-page-heading">
    <div><span class="section-kicker">ADMINISTRATION</span><h1>Users</h1><p>Manage accounts, roles, and access status.</p></div>
    @if (auth()->user()->hasPermission('users', 'create'))
        <a class="button button-primary" href="{{ route('users.create') }}"><span class="button-plus">+</span> Add User</a>
    @endif
</div>
@include('components.flash')
<section class="panel workspace-panel">
    <form class="workspace-toolbar" method="GET" action="{{ route('users.index') }}">
        <label class="search-field workspace-search"><span class="search-glyph" aria-hidden="true">⌕</span><input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, email or phone..." aria-label="Search users"></label>
        <select class="filter-select" name="status" aria-label="Filter users by status">
            <option value="">All</option><option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option><option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button class="button button-primary" type="submit">Apply</button>
        @if (request()->hasAny(['search', 'status']))<a class="button button-light" href="{{ route('users.index') }}">Reset</a>@endif
    </form>
    <div class="table-wrap">
        <table class="data-table workspace-table">
            <thead><tr><th>NAME</th><th>EMAIL</th><th>PHONE</th><th>ROLE</th><th>STATUS</th><th>CREATED</th><th>ACTIONS</th></tr></thead>
            <tbody>
            @forelse ($users as $user)
                <tr>
                    <td><strong>{{ $user->name }}</strong></td><td>{{ $user->email }}</td><td>{{ $user->phone ?: '—' }}</td>
                    <td>{{ optional($user->roles->first())->label ?: $user->role }}</td>
                    <td>@include('components.status-badge', ['status' => $user->is_active ? 'Active' : 'Inactive'])</td>
                    <td>{{ optional($user->created_at)->format('d M Y') ?: '—' }}</td>
                    <td class="action-cell">
                        <a class="button button-small button-light" href="{{ route('users.show', $user->id) }}">View</a>
                        @if (auth()->user()->hasPermission('users', 'edit'))<a class="button button-small button-light" href="{{ route('users.edit', $user->id) }}">Edit</a>@endif
                        @if (auth()->user()->hasPermission('users', 'delete'))
                        <form class="inline-form" method="POST" action="{{ route('users.status', $user->id) }}" onsubmit="return confirm('{{ $user->is_active ? 'Are you sure you want to deactivate this user?' : 'Activate this user?' }}')">
                            @csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $user->is_active ? 0 : 1 }}">
                            <button class="button button-small {{ $user->is_active ? 'button-deactivate' : 'button-activate' }}" type="submit">{{ $user->is_active ? 'Deactivate' : 'Activate' }}</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No users found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($users->hasPages())<footer class="workspace-footer"><span>Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span><div class="workspace-pagination">{{ $users->onEachSide(2)->links() }}</div></footer>@endif
</section>
@endsection
