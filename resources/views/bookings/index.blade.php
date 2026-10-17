@extends('layouts.app')

@section('title', 'Bookings | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Bookings" description="Your rental dates and booking status, together in one place." />
        <div class="table-responsive info-panel overflow-hidden" role="region" tabindex="0" aria-label="Bookings table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Space</th>
                        <th>Renter</th>
                        <th>Dates</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td>{{ $booking->space->space_name }}</td>
                            <td>{{ $booking->renter->name }}</td>
                            <td>{{ $booking->start_date->format('M j, Y') }} - {{ $booking->end_date->format('M j, Y') }}</td>
                            <td><x-status-badge :status="$booking->status" /></td>
                            @if(auth()->user()->hasRole('renter'))
                                <td>
                                    @foreach($booking->informationRequests ?? [] as $item)<div class="small mb-2"><strong>Owner asks:</strong> {{ $item->question }}@if($item->response)<br><strong>Your reply:</strong> {{ $item->response }}@endif</div>@if(!$item->responded_at && $booking->status === 'awaiting_renter_information')<form method="POST" action="{{ route('booking-information.respond', $item) }}" class="d-flex gap-2">@csrf<textarea class="form-control form-control-sm" name="response" rows="2" required maxlength="3000" placeholder="Reply to the owner"></textarea><button class="btn btn-outline-dark btn-sm" type="submit">Send</button></form>@endif @endforeach
                                    @if($booking->decision_reason)<span class="small text-muted-soft">{{ $booking->decision_reason }}</span>@endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->hasRole('renter') ? 5 : 4 }}" class="record-empty"><x-empty-state title="No rental activity yet" message="Start exploring available spaces across the Philippines." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$bookings" /></div>
    </div>
@endsection
