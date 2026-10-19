@extends('layouts.app')
@section('title', 'Rental requests and active rentals | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <x-record-heading title="Rental requests and rentals" description="Review requests, ask follow-up questions, and monitor confirmed rentals for your properties." />
    <div class="d-grid gap-3">
    @forelse($bookings as $booking)
        <article class="panel p-4">
            <div class="d-flex flex-wrap justify-content-between gap-3"><div><div class="eyebrow">{{ $booking->space->property->property_name }} · {{ $booking->space->space_name }}</div><h2 class="panel-title mt-2 mb-1">{{ $booking->renter->name }}</h2><p class="small text-muted-soft mb-0">{{ $booking->renter->email }} · {{ $booking->start_date->format('M j, Y') }} – {{ $booking->end_date->format('M j, Y') }} · {{ $booking->occupants ?? '—' }} occupant(s)</p></div><x-status-badge :status="$booking->status" /></div>
            @if($booking->renter_message)<p class="mt-3 mb-0">{{ $booking->renter_message }}</p>@endif
            <div class="small mt-3"><strong>Requested amount:</strong> ₱{{ number_format((float) ($booking->agreed_amount ?? 0), 2) }}</div>
            @if($booking->decision_reason)<p class="small text-muted-soft mt-2 mb-0">Decision note: {{ $booking->decision_reason }}</p>@endif
            @if($booking->informationRequests->isNotEmpty())<div class="border-top mt-3 pt-3"><h3 class="small fw-semibold">Additional information history</h3>@foreach($booking->informationRequests as $item)<div class="small mb-2"><strong>Owner:</strong> {{ $item->question }}<br><strong>Renter:</strong> {{ $item->response ?? 'Waiting for renter response' }}</div>@endforeach</div>@endif
            @if($booking->status === 'pending')
                <div class="d-flex flex-wrap gap-2 mt-3"><form method="POST" action="{{ route('owner.bookings.decision', $booking) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-primary-custom" type="submit">Approve and confirm booking</button></form><form method="POST" action="{{ route('owner.bookings.decision', $booking) }}">@csrf<input type="hidden" name="decision" value="reject"><input class="form-control form-control-sm mb-2" name="reason" maxlength="2000" placeholder="Optional rejection reason"><button class="btn btn-outline-danger" type="submit">Reject request</button></form><form method="POST" action="{{ route('owner.bookings.decision', $booking) }}" class="d-flex flex-wrap gap-2">@csrf<input type="hidden" name="decision" value="more_details"><input class="form-control form-control-sm" name="reason" required maxlength="2000" placeholder="Question for the renter"><button class="btn btn-outline-dark" type="submit">Request more details</button></form></div>
            @endif
            @if($booking->status === 'confirmed' && $booking->start_date->toDateString() <= now()->toDateString() && $booking->end_date->toDateString() >= now()->toDateString())<form method="POST" action="{{ route('owner.bookings.activate', $booking) }}" class="mt-3">@csrf @method('PATCH')<button class="btn btn-outline-dark" type="submit">Mark rental active</button></form>@endif
            @if(in_array($booking->status, ['confirmed', 'active']) && $booking->end_date->copy()->endOfDay()->isPast())<form method="POST" action="{{ route('owner.bookings.complete', $booking) }}" class="mt-3">@csrf @method('PATCH')<button class="btn btn-outline-dark" type="submit">Complete rental</button></form>@endif
        </article>
    @empty
        <x-empty-state title="No rental activity yet" message="Rental requests for your published spaces will appear here." :href="route('owner.properties.index')" action="View my properties" />
    @endforelse
    </div>
    <div class="mt-4"><x-pagination :paginator="$bookings" /></div>
</div>
@endsection
