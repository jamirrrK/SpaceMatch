@extends('layouts.app')
@section('title', 'Administrator access | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="eyebrow mb-2">Restricted master administrator workspace</div>
    <h1 class="page-heading mb-2">Administrator access</h1>
    <p class="small text-muted-soft mb-4">Only existing administrators with verified contact details can receive master administrator access. Keep at least one active master administrator.</p>
    <div class="d-grid gap-3">
        @forelse($administrators as $administrator)
            <article class="panel p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="panel-title mb-1">{{ $administrator->name }}</h2>
                        <p class="small text-muted-soft mb-1">{{ $administrator->email }}</p>
                        <span class="badge {{ $administrator->hasRole(\App\Models\User::ROLE_MASTER_ADMIN) ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $administrator->hasRole(\App\Models\User::ROLE_MASTER_ADMIN) ? 'Master administrator' : 'Administrator' }}</span>
                        @if($administrator->requiresContactVerification())<span class="badge text-bg-warning">Contact verification required</span>@endif
                    </div>
                    @if($administrator->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.master-admins.update', $administrator) }}" data-confirm="Change administrator access for {{ $administrator->email }}? This action will be recorded.">
                            @csrf
                            @method('PATCH')
                            @if($administrator->hasRole(\App\Models\User::ROLE_MASTER_ADMIN))
                                <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_ADMIN }}">
                                <button class="btn btn-outline-danger" type="submit">Remove master access</button>
                            @else
                                <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_MASTER_ADMIN }}">
                                <button class="btn btn-primary-custom" type="submit" @disabled($administrator->requiresContactVerification())>Grant master access</button>
                            @endif
                        </form>
                    @else
                        <span class="small text-muted-soft">Your active account</span>
                    @endif
                </div>
            </article>
        @empty
            <x-empty-state title="No administrator accounts found" message="Use the verified, interactive Artisan provisioning command to grant initial master administrator access." />
        @endforelse
    </div>
    <div class="mt-4"><x-pagination :paginator="$administrators" /></div>
</div>
@endsection
