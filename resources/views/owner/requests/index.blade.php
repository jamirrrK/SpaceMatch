@extends('layouts.app')
@section('title', 'Rental requests | SpaceMatch')
@section('content')
<div class="container py-4 py-lg-5">
    <div class="my-4"><div class="eyebrow mb-2">Owner workspace</div><h1 class="page-heading mb-1">Rental requests</h1><p class="text-muted-soft mb-0">Review requests for your listings and make an explicit decision.</p></div>
    <div class="d-grid gap-3">
        @forelse($requests as $rentalRequest)
            <article class="panel p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                    <div>
                        <div class="eyebrow">{{ $rentalRequest->space?->property?->property_name }}</div>
                        <h2 class="listing-title fs-5 mt-1">{{ $rentalRequest->space?->space_name }}</h2>
                        <p class="mb-1">{{ $rentalRequest->renter?->name }} · {{ $rentalRequest->contact_phone }}</p>
                        <p class="small text-muted-soft mb-2">{{ $rentalRequest->move_in_date->format('M j, Y') }} · {{ $rentalRequest->occupants }} {{ \Illuminate\Support\Str::plural('occupant', $rentalRequest->occupants) }} · {{ $rentalRequest->duration_months }} months</p>
                        <p class="small mb-1">Requested monthly rent: ₱{{ number_format((float) ($rentalRequest->space?->price ?? 0), 2) }}</p>
                        @if($rentalRequest->message)<p class="mb-2"><strong>Renter message:</strong> {{ $rentalRequest->message }}</p>@endif
                        @if($rentalRequest->renter_response)<p class="mb-2"><strong>Renter follow-up:</strong> {{ $rentalRequest->renter_response }}</p>@endif
                        @if($rentalRequest->response_message)<p class="mb-2"><strong>Your last response:</strong> {{ $rentalRequest->response_message }}</p>@endif
                    </div>
                    <x-status-badge :status="$rentalRequest->status" />
                </div>
                @if($rentalRequest->booking)
                    <p class="small mb-0">Booking {{ $rentalRequest->booking->reference }} · confirmed for {{ $rentalRequest->booking->start_date->format('M j, Y') }} to {{ $rentalRequest->booking->end_date->format('M j, Y') }}.</p>
                @elseif(in_array($rentalRequest->status, [\App\Models\RentalRequest::STATUS_PENDING, \App\Models\RentalRequest::STATUS_MORE_INFORMATION], true))
                    <form class="border-top pt-3 mt-3" method="POST" action="{{ route('owner.requests.update', $rentalRequest) }}">
                        @csrf @method('PATCH')
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4"><label class="form-label" for="decision-{{ $rentalRequest->id }}">Decision</label><select class="form-select" id="decision-{{ $rentalRequest->id }}" name="status" required><option value="approved">Approve and confirm booking</option><option value="rejected">Reject request</option><option value="more_information_requested">Request more information</option></select></div>
                            <div class="col-md-6"><label class="form-label" for="owner-response-{{ $rentalRequest->id }}">Message to renter</label><textarea class="form-control" id="owner-response-{{ $rentalRequest->id }}" name="response_message" rows="2" maxlength="2000" placeholder="Required for rejection or more information request"></textarea></div>
                            <div class="col-md-2"><button class="btn btn-primary-custom w-100" type="submit">Send decision</button></div>
                        </div>
                        <p class="small text-muted-soft mt-2 mb-0">Approval checks overlapping confirmed stays and capacity, then creates a booking. It does not process payment.</p>
                    </form>
                @endif
            </article>
        @empty
            <x-empty-state title="No rental requests" message="Requests for your properties will appear here." :href="route('owner.dashboard')" action="Back to dashboard" />
        @endforelse
    </div>
    <x-pagination :paginator="$requests" />
</div>
@endsection
