@extends('layouts.app')

@section('title', 'Maintenance Requests | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Maintenance requests" description="Keep track of recorded issues and their current status." />
        @if(auth()->user()->hasRole('renter') && $rentals->isNotEmpty())<section class="panel p-4 mb-4"><h2 class="panel-title mb-3">Report a rental issue</h2><form method="POST" action="{{ route('maintenance.store') }}" class="row g-3">@csrf<div class="col-md-5"><label class="form-label" for="booking_id">Rental</label><select class="form-select" name="booking_id" id="booking_id" required>@foreach($rentals as $rental)<option value="{{ $rental->id }}">{{ $rental->space->property->property_name }} · {{ $rental->space->space_name }}</option>@endforeach</select></div><div class="col-md-7"><label class="form-label" for="description">Describe the issue</label><textarea class="form-control" id="description" name="description" rows="2" required minlength="5" maxlength="5000"></textarea></div><div class="col-12"><button class="btn btn-primary-custom" type="submit">Send maintenance request</button></div></form></section>@endif
        <div class="table-responsive info-panel overflow-hidden" role="region" tabindex="0" aria-label="Maintenance requests table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Issue</th>
                        <th>Status</th>
                        <th>{{ auth()->user()->hasRole('owner') ? 'Resolve / respond' : 'Owner response' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->booking->space->space_name }}</td>
                            <td>{{ $request->description }}</td>
                            <td><x-status-badge :status="$request->status" /></td>
                            @if(auth()->user()->hasRole('owner'))<td><form method="POST" action="{{ route('owner.maintenance.update', $request) }}" class="d-grid gap-2">@csrf @method('PATCH')<select class="form-select form-select-sm" name="status"><option value="open" @selected($request->status === 'open')>Open</option><option value="in_progress" @selected($request->status === 'in_progress')>In progress</option><option value="resolved" @selected($request->status === 'resolved')>Resolved</option></select><textarea class="form-control form-control-sm" name="owner_response" rows="2" maxlength="3000" placeholder="Response or resolution">{{ $request->owner_response }}</textarea><button class="btn btn-outline-dark btn-sm" type="submit">Update renter</button></form>@if($request->owner_response)<div class="small text-muted-soft mt-2">{{ $request->owner_response }}</div>@endif</td>@elseif($request->owner_response)<td>{{ $request->owner_response }}</td>@endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->hasRole('owner') ? 4 : 3 }}" class="record-empty"><x-empty-state title="No maintenance requests yet" message="Maintenance records associated with your rentals will appear here." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$requests" /></div>
    </div>
@endsection
