@extends('adminlte::page')

@section('title', 'User Detail')

@section('content_header')
    <h1>User Detail</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
            {{ session('success') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Account Info</h3>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th style="width:180px">ID</th>
                            <td>{{ $user->id }}</td>
                        </tr>
                        <tr>
                            <th>Name</th>
                            <td>
                                {{ $user->name }}
                                @if ($user->deleted)
                                    <span class="badge badge-secondary">Deleted</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Username</th>
                            <td>{{ $user->username }}</td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td>{{ $user->email }}</td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge badge-success">Active</span>
                                @else
                                    <span class="badge badge-danger">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Deletion Status</th>
                            <td>
                                @if ($user->deleted)
                                    <span class="badge badge-secondary">Deleted</span>
                                    <small class="text-muted">({{ $user->deleted_at?->format('Y-m-d H:i') }})</small>
                                @else
                                    <span class="badge badge-success">Not deleted</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Email Verified</th>
                            <td>
                                @if ($user->email_verified_at)
                                    {{ $user->email_verified_at->format('Y-m-d H:i') }}
                                @else
                                    <span class="badge badge-warning">Not verified</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Registered</th>
                            <td>{{ $user->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        <tr>
                            <th>Last Updated</th>
                            <td>{{ $user->updated_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Actions</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.users.toggle', $user->id) }}" method="POST" class="mb-2">
                        @csrf
                        @method('PATCH')
                        <button type="submit"
                            class="btn btn-{{ $user->is_active ? 'warning' : 'success' }} btn-block"
                            onclick="return confirm('{{ $user->is_active ? 'Deactivate' : 'Activate' }} this user?')">
                            <i class="fas fa-{{ $user->is_active ? 'ban' : 'check' }}"></i>
                            {{ $user->is_active ? 'Deactivate User' : 'Activate User' }}
                        </button>
                    </form>

                    <form action="{{ route('admin.users.destroy', $user->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-block"
                            onclick="return confirm('Permanently delete this user? This action cannot be undone.')">
                            <i class="fas fa-trash"></i>
                            Delete User Permanently
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Projects</h3>
                </div>
                <div class="card-body">
                    <p><strong>Owned projects:</strong> {{ $user->ownedProjects->count() }}</p>
                    <p><strong>Member of:</strong> {{ $user->projects->count() }} projects</p>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Send Warning</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.users.warn', $user->id) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <textarea name="message" class="form-control @error('message') is-invalid @enderror"
                                rows="3" placeholder="Enter warning message..." required></textarea>
                            @error('message')
                                <span class="invalid-feedback">{{ $message }}</span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-warning btn-block">
                            <i class="fas fa-exclamation-triangle"></i>
                            Send Warning
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <a href="{{ url()->previous() ?: route('admin.users.index') }}" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>
@stop
