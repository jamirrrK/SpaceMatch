@extends('layouts.app')
@section('title', 'My rental requests | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="my-4"><div class="eyebrow mb-2">Rental journey</div><h1 class="page-heading mb-1">My rental requests</h1><p class="text-muted-soft mb-0">Follow each request and respond when an owner asks for more information.</p></div>
    <div class="d-grid gap-3">
        @forelse($requests as $rentalRequest)
            <article class="panel p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="eyebrow">{{ $rentalRequest->space?->property?->property_name ?? 'Property unavailable' }}</div>
                        <h2 class="listing-title fs-5 mt-1">{{ $rentalRequest->space?->space_name ?? 'Space unavailable' }}</h2>
                        <p class="small text-muted-soft mb-2">Move-in {{ $rentalRequest->move_in_date->format('M j, Y') }} · {{ $rentalRequest->occupants }} {{ \Illuminate\Support\Str::plural('occupant', $rentalRequest->occupants) }} · {{ $rentalRequest->duration_months }} months</p>
                        <p class="small mb-2">Monthly rate at listing: ₱{{ number_format((float) ($rentalRequest->space?->price ?? 0), 2) }}</p>
                    </div>
                    <x-status-badge :status="$rentalRequest->status" />
                </div>
                @if($rentalRequest->message)<p class="mb-2"><strong>Your message:</strong> {{ $rentalRequest->message }}</p>@endif
                @if($rentalRequest->response_message)<p class="mb-2"><strong>Owner response:</strong> {{ $rentalRequest->response_message }}</p>@endif
                @if($rentalRequest->renter_response)<p class="mb-2"><strong>Your follow-up:</strong> {{ $rentalRequest->renter_response }}</p>@endif
                @if($rentalRequest->statusHistory->isNotEmpty())
                    <details class="small mt-3">
                        <summary>Request history</summary>
                        <ol class="mt-2 ps-3">
                            @foreach($rentalRequest->statusHistory as $history)
                                <li>{{ $history->created_at->format('M j, Y g:i A') }} · {{ ucfirst(str_replace('_', ' ', $history->event)) }}
                                    @if($history->from_status) · {{ ucfirst(str_replace('_', ' ', $history->from_status)) }} → {{ ucfirst(str_replace('_', ' ', $history->to_status)) }}@endif
                                    @if($history->notes)<span class="d-block text-muted-soft">{{ $history->notes }}</span>@endif
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
                @if($rentalRequest->booking)
                    <p class="small mb-2">Booking reference: <strong>{{ $rentalRequest->booking->reference }}</strong> · {{ $rentalRequest->booking->start_date->format('M j, Y') }} – {{ $rentalRequest->booking->end_date->format('M j, Y') }}</p>
                    <a class="small-link" href="{{ route('bookings.index') }}">View your booking</a>
                @endif
                @if($rentalRequest->status === \App\Models\RentalRequest::STATUS_MORE_INFORMATION)
                    <form class="mt-3" method="POST" action="{{ route('renter.requests.respond', $rentalRequest) }}">@csrf @method('PATCH')
                        <label class="form-label" for="response-{{ $rentalRequest->id }}">Send the owner more information</label>
                        <textarea class="form-control mb-2" id="response-{{ $rentalRequest->id }}" name="renter_response" rows="3" maxlength="2000" required></textarea>
                        <button class="btn btn-primary-custom btn-sm" type="submit">Send response</button>
                    </form>
                @endif
                @if(in_array($rentalRequest->status, [\App\Models\RentalRequest::STATUS_PENDING, \App\Models\RentalRequest::STATUS_MORE_INFORMATION], true))
                    <form class="mt-3" method="POST" action="{{ route('renter.requests.cancel', $rentalRequest) }}" data-confirm="Cancel this rental request?">@csrf @method('PATCH')<button class="btn btn-outline-dark btn-sm" type="submit">Cancel request</button></form>
                @endif
            </article>
        @empty
            <x-empty-state title="No rental requests yet" message="Choose an available rental space and send a request to its owner." :href="route('properties.index')" action="Browse rentals" />
        @endforelse
    </div>
    <x-pagination :paginator="$requests" />
</div>
@endsection
