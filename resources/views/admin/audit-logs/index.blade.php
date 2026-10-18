@extends('layouts.app')
@section('title', 'Audit log | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">Administration</div>
        <h1 class="page-heading mb-1">Audit trail</h1>
        <p class="small text-muted-soft mb-0">Review admin and platform actions recorded across the system.</p>
    </div>

    <form class="panel p-4 mb-4" method="GET" action="{{ route('admin.audit-logs.index') }}">
        <div class="row g-3 align-items-end">
            <div class="col-md-8">
                <label class="form-label" for="admin-audit-search">Search</label>
                <input class="form-control" type="search" name="q" id="admin-audit-search" value="{{ $filters['q'] ?? '' }}" placeholder="Action, description, user name, or email">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="admin-audit-action">Action</label>
                <input class="form-control" type="text" name="action" id="admin-audit-action" value="{{ $filters['action'] ?? '' }}" placeholder="property.status_changed">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary-custom w-100" type="submit">Search</button>
            </div>
        </div>
    </form>

    @if($auditLogs->isNotEmpty())
        <section class="panel overflow-hidden">
            <div class="table-wrap" role="region" tabindex="0" aria-label="Audit log records table">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Action</th>
                            <th scope="col">Actor</th>
                            <th scope="col">Description</th>
                            <th scope="col">Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $log)
                            <tr>
                                <td><span class="amenity-chip">{{ $log->action }}</span></td>
                                <td>
                                    <div class="small fw-semibold">{{ $log->user?->name ?? 'System' }}</div>
                                    <div class="table-sub">{{ $log->user?->email ?? '—' }}</div>
                                </td>
                                                        <td class="table-sub">{{ $log->readable_description }}</td>
                                <td class="table-sub">{{ $log->created_at?->format('M d, Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        <x-pagination :paginator="$auditLogs" />
    @else
        <x-empty-state title="No audit entries found" message="No moderation or account actions have been logged yet." :href="route('admin.audit-logs.index')" action="Reset filters" />
    @endif
</div>
@endsection
