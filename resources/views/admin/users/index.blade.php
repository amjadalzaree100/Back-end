@extends('adminlte::page')

@section('title', 'Users')

@section('content_header')
    <h1>Users</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            @php
                // Build sortable header URLs by toggling the current sort key.
                $sortLink = function (string $column, string $ascValue, string $descValue) use ($sort) {
                    $next = ($sort === $ascValue) ? $descValue : $ascValue;
                    return request()->fullUrlWithQuery(['sort' => $next, 'page' => 1]);
                };
                $sortCaret = function (string $column, string $ascValue, string $descValue) use ($sort) {
                    if ($sort === $ascValue) { return '▲'; }
                    if ($sort === $descValue) { return '▼'; }
                    return '';
                };
            @endphp
            @push('extra_filters')
                <div class="input-group input-group-sm mr-2 mb-2" style="max-width: 260px;">
                    <input type="text" name="search" class="form-control" placeholder="Search name, email, username..."
                        value="{{ request('search') }}">
                </div>

                <select name="status" class="form-control form-control-sm mr-2 mb-2" data-auto-submit>
                    <option value="">All statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>

                <select name="email_verified" class="form-control form-control-sm mr-2 mb-2" data-auto-submit>
                    <option value="all" {{ ($verified ?? 'all') === 'all' ? 'selected' : '' }}>All verified</option>
                    <option value="yes" {{ ($verified ?? '') === 'yes' ? 'selected' : '' }}>Verified</option>
                    <option value="no"  {{ ($verified ?? '') === 'no'  ? 'selected' : '' }}>Not verified</option>
                </select>

                <select name="sort" class="form-control form-control-sm mr-2 mb-2" data-auto-submit>
                    <option value="newest"    {{ $sort === 'newest'    ? 'selected' : '' }}>Newest first</option>
                    <option value="oldest"    {{ $sort === 'oldest'    ? 'selected' : '' }}>Oldest first</option>
                    <option value="name_asc"  {{ $sort === 'name_asc'  ? 'selected' : '' }}>Name A→Z</option>
                    <option value="name_desc" {{ $sort === 'name_desc' ? 'selected' : '' }}>Name Z→A</option>
                </select>

                <select name="per_page" class="form-control form-control-sm mr-2 mb-2" data-auto-submit title="Per page">
                    @foreach ([15, 30, 50] as $n)
                        <option value="{{ $n }}" {{ (int) $perPage === $n ? 'selected' : '' }}>{{ $n }} / page</option>
                    @endforeach
                </select>
            @endpush

            @include('admin.partials._filter-bar', ['resetRoute' => route('admin.users.index')])

            <div class="d-flex align-items-center flex-wrap mt-2">
                <button type="submit" form="bulk-notification-form"
                    class="btn btn-sm btn-primary mr-2 mb-2" id="btn-send-notification" disabled>
                    <i class="fas fa-paper-plane"></i> Send Notification (<span id="selected-count">0</span>)
                </button>
                <small class="text-muted mb-2">Select users below to send a notification.</small>
            </div>
        </div>

        <div class="card-body table-responsive p-0">
            <form id="bulk-notification-form" action="{{ route('admin.users.notify.create') }}" method="GET">
            {{-- Forward active filters so they survive the round-trip to the compose page and back. --}}
            @foreach (request()->only(['search', 'status', 'email_verified', 'sort', 'per_page', 'date_from', 'date_to', 'page']) as $key => $value)
                @if (is_string($value) || is_numeric($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endif
            @endforeach
            <table class="table table-hover text-nowrap">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-page">
                        </th>
                        <th>ID</th>
                        <th>
                            <a href="{{ $sortLink('name', 'name_asc', 'name_desc') }}" class="text-dark">
                                Name {{ $sortCaret('name', 'name_asc', 'name_desc') }}
                            </a>
                        </th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Status</th>
                        <th>Verified</th>
                        <th>
                            <a href="{{ $sortLink('registered', 'oldest', 'newest') }}" class="text-dark">
                                Registered {{ $sortCaret('registered', 'oldest', 'newest') }}
                            </a>
                        </th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>
                                <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="user-checkbox">
                            </td>
                            <td>{{ $user->id }}</td>
                            <td>
                                {{ $user->name }}
                                @if ($user->deleted)
                                    <span class="badge badge-secondary">Deleted</span>
                                @endif
                            </td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $user->username }}</td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                            <td>
                                @if ($user->email_verified_at)
                                    <span class="badge badge-success">Yes</span>
                                @else
                                    <span class="badge badge-warning">Not verified</span>
                                @endif
                            </td>
                            <td>{{ $user->created_at->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('admin.users.show', $user->id) }}" class="btn btn-xs btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form action="{{ route('admin.users.toggle', $user->id) }}" method="POST"
                                    style="display:inline">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-xs btn-{{ $user->is_active ? 'warning' : 'success' }}"
                                        onclick="return confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} this user?')">
                                        <i class="fas fa-{{ $user->is_active ? 'ban' : 'check' }}"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST"
                                    style="display:inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-xs btn-danger"
                                        onclick="return confirm('Permanently delete this user? This cannot be undone.')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">No users found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </form>
        </div>

        @if ($users->hasPages())
            <div class="card-footer clearfix">
                {{ $users->links() }}
            </div>
        @endif
    </div>

    @push('js')
    <script>
        (function () {
            const selectAll = document.getElementById('select-all-page');
            const checkboxes = document.querySelectorAll('.user-checkbox');
            const btn = document.getElementById('btn-send-notification');
            const countSpan = document.getElementById('selected-count');

            function updateCount() {
                const checked = document.querySelectorAll('.user-checkbox:checked').length;
                countSpan.textContent = checked;
                btn.disabled = checked === 0;
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    updateCount();
                });
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    if (!cb.checked) selectAll.checked = false;
                    else if (document.querySelectorAll('.user-checkbox:checked').length === checkboxes.length) {
                        selectAll.checked = true;
                    }
                    updateCount();
                });
            });
        })();
    </script>
    @endpush
@stop
