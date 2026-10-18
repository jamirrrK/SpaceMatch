@extends('layouts.app')

@section('title', 'Maintenance Requests | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Maintenance requests" description="Keep track of recorded issues and their current status." />
        <div class="table-responsive info-panel" role="region" tabindex="0" aria-label="Maintenance requests table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Rental</th>
                        <th scope="col">Issue</th>
                        <th scope="col">Status and history</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->booking->space->property->property_name }}<div class="small text-muted-soft">{{ $request->booking->space->space_name }} · {{ $request->booking->reference }}</div><div class="small text-muted-soft">Renter: {{ $request->booking->renter->name }}</div></td>
                            <td>{{ $request->description }}</td>
                            <td>
                                <x-status-badge :status="$request->status" />
                                @if($request->statusHistory->isNotEmpty())
                                    <details class="small mt-2">
                                        <summary>Update history</summary>
                                        <ul class="mt-2 ps-3">
                                            @foreach($request->statusHistory as $history)
                                                <li>{{ $history->created_at->format('M j, Y') }}: {{ ucfirst(str_replace('_', ' ', $history->from_status)) }} → {{ ucfirst(str_replace('_', ' ', $history->to_status)) }} by {{ $history->actor->name }}
                                                    @if($history->notes)<span class="d-block text-muted-soft">{{ $history->notes }}</span>@endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                                @if(auth()->user()->role === \App\Models\User::ROLE_OWNER && $request->status !== 'closed')
                                    @php($nextStatus = ['open' => 'in_progress', 'in_progress' => 'resolved', 'resolved' => 'closed'][$request->status] ?? null)
                                    @if($nextStatus)
                                        <form class="border-top mt-3 pt-3" method="POST" action="{{ route('owner.maintenance.update', $request) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="{{ $nextStatus }}">
                                            @if($nextStatus === 'resolved')
                                                <label class="form-label small" for="maintenance-notes-{{ $request->id }}">Resolution notes</label>
                                                <textarea class="form-control form-control-sm mb-2" id="maintenance-notes-{{ $request->id }}" name="notes" rows="2" maxlength="2000" required></textarea>
                                            @endif
                                            <button class="btn btn-outline-dark btn-sm" type="submit">Mark {{ str_replace('_', ' ', $nextStatus) }}</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="record-empty"><x-empty-state title="No maintenance requests yet" message="Maintenance records associated with your rentals will appear here." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$requests" /></div>
    </div>
@endsection
