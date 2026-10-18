@extends('layouts.app')
@section('title', 'Users | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">Administration</div>
        <h1 class="page-heading mb-1">User directory</h1>
        <p class="small text-muted-soft mb-0">Search and review renter and owner accounts.</p>
    </div>

    <form class="panel p-4 mb-4" method="GET" action="{{ route('admin.users.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label class="form-label" for="admin-user-search">Search</label>
                <input class="form-control" type="search" name="q" id="admin-user-search" value="{{ $filters['q'] ?? '' }}" placeholder="Name, email, or phone number">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="admin-user-role">Role</label>
                <select class="form-select" name="role" id="admin-user-role">
                    <option value="">All roles</option>
                    <option value="renter" @selected(($filters['role'] ?? '') === 'renter')>Renter</option>
                    <option value="owner" @selected(($filters['role'] ?? '') === 'owner')>Property owner</option>
                    <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Administrator</option>
                    <option value="master_admin" @selected(($filters['role'] ?? '') === 'master_admin')>Master administrator</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="admin-user-verification">Verification</label>
                <select class="form-select" name="verification" id="admin-user-verification">
                    <option value="">All</option>
                    <option value="pending" @selected(($filters['verification'] ?? '') === 'pending')>Pending</option>
                    <option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>Verified</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary-custom w-100" type="submit">Search</button>
            </div>
        </div>
    </form>

    @if($users->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="p-4">
                <h2 class="panel-title mb-0">{{ number_format($users->total()) }} {{ $users->total() === 1 ? 'user' : 'users' }}</h2>
            </div>
            <div class="table-wrap" role="region" tabindex="0" aria-label="User records table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">User</th>
                            <th scope="col">Role</th>
                            <th scope="col">Contact</th>
                            <th scope="col">Verification</th>
                            <th scope="col">Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td>
                                    <div class="small fw-semibold">{{ $user->name }}</div>
                                    <div class="table-sub">{{ $user->email }}</div>
                                </td>
                                <td><span class="amenity-chip">{{ ucfirst($user->role) }}</span></td>
                                <td>
                                    <div class="table-sub">{{ $user->phone_number ?: 'No mobile number on file' }}</div>
                                </td>
                                <td>
                                    @if($user->verification_required_at)
                                        <x-status-badge status="pending" />
                                    @else
                                        <x-status-badge status="active" />
                                    @endif
                                </td>
                                <td class="table-sub">{{ $user->created_at?->format('M d, Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <x-pagination :paginator="$users" />
    @else
        <x-empty-state title="No users found" message="Try changing the filters to widen your search." :href="route('admin.users.index')" action="Reset filters" />
    @endif
</div>
@endsection
