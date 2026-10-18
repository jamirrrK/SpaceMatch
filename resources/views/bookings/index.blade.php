@extends('layouts.app')

@section('title', 'Bookings | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Bookings" description="Your rental dates and booking status, together in one place." />
        <div class="table-responsive info-panel" role="region" tabindex="0" aria-label="Bookings table">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Space</th>
                        <th>Renter</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th>Rental / review action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $booking)
                        <tr>
                            <td><a href="{{ route('bookings.show', $booking) }}">{{ $booking->space->space_name }}</a>@if($booking->reference)<div class="small text-muted-soft">{{ $booking->reference }}</div>@endif</td>
                            <td>{{ $booking->renter->name }}</td>
                            <td>{{ $booking->start_date->format('M j, Y') }} - {{ $booking->end_date->format('M j, Y') }}</td>
                            <td><x-status-badge :status="$booking->status" />
                                @if($booking->statusHistory->isNotEmpty())
                                    <details class="small mt-2">
                                        <summary>Booking history</summary>
                                        <ul class="mt-2 ps-3">
                                            @foreach($booking->statusHistory as $history)
                                                <li>{{ $history->created_at->format('M j, Y') }}:
                                                    {{ $history->from_status ? ucfirst($history->from_status).' → ' : '' }}{{ ucfirst($history->to_status) }}
                                                    @if($history->notes)<span class="d-block text-muted-soft">{{ $history->notes }}</span>@endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </details>
                                @endif
                            </td>
                            <td>
                                @if(auth()->user()->role === \App\Models\User::ROLE_OWNER && in_array($booking->status, ['confirmed', 'active'], true))
                                    @php($nextStatus = $booking->status === 'confirmed' ? 'active' : 'completed')
                                    <form method="POST" action="{{ route('owner.bookings.update', $booking) }}" data-confirm="Move this booking to {{ $nextStatus }}?">@csrf @method('PATCH')
                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                        <button class="btn btn-outline-dark btn-sm" type="submit">{{ $booking->status === 'confirmed' ? 'Mark moved in' : 'Complete rental' }}</button>
                                    </form>
                                    @if($booking->status === 'confirmed' && $booking->start_date->isFuture())
                                        <details class="mt-2">
                                            <summary class="small">Cancel booking</summary>
                                            <form class="mt-2" method="POST" action="{{ route('owner.bookings.cancel', $booking) }}">
                                                @csrf @method('PATCH')
                                                <label class="form-label small" for="owner-cancel-reason-{{ $booking->id }}">Reason</label>
                                                <textarea class="form-control form-control-sm mb-2" id="owner-cancel-reason-{{ $booking->id }}" name="reason" minlength="5" maxlength="1000" required></textarea>
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Confirm cancellation</button>
                                            </form>
                                        </details>
                                    @elseif($booking->status === 'active')
                                        <details class="mt-2">
                                            <summary class="small">Terminate rental</summary>
                                            <form class="mt-2" method="POST" action="{{ route('owner.bookings.terminate', $booking) }}">
                                                @csrf @method('PATCH')
                                                <label class="form-label small" for="owner-terminate-reason-{{ $booking->id }}">Reason</label>
                                                <textarea class="form-control form-control-sm mb-2" id="owner-terminate-reason-{{ $booking->id }}" name="reason" minlength="5" maxlength="1000" required></textarea>
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Confirm termination</button>
                                            </form>
                                        </details>
                                    @endif
                                @elseif(auth()->user()->role === \App\Models\User::ROLE_RENTER && $booking->status === 'confirmed' && $booking->start_date->isFuture())
                                    <span class="small text-muted-soft d-block mb-2">Move-in {{ $booking->start_date->format('M j, Y') }}</span>
                                    <details>
                                        <summary class="small">Cancel booking</summary>
                                        <form class="mt-2" method="POST" action="{{ route('renter.bookings.cancel', $booking) }}">
                                            @csrf @method('PATCH')
                                            <label class="form-label small" for="renter-cancel-reason-{{ $booking->id }}">Reason</label>
                                            <textarea class="form-control form-control-sm mb-2" id="renter-cancel-reason-{{ $booking->id }}" name="reason" minlength="5" maxlength="1000" required></textarea>
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Confirm cancellation</button>
                                        </form>
                                    </details>
                                @elseif(auth()->user()->role === \App\Models\User::ROLE_RENTER && $booking->status === 'active')
                                    <details>
                                        <summary class="small">Terminate rental</summary>
                                        <form class="mt-2" method="POST" action="{{ route('renter.bookings.terminate', $booking) }}">
                                            @csrf @method('PATCH')
                                            <label class="form-label small" for="renter-terminate-reason-{{ $booking->id }}">Reason</label>
                                            <textarea class="form-control form-control-sm mb-2" id="renter-terminate-reason-{{ $booking->id }}" name="reason" minlength="5" maxlength="1000" required></textarea>
                                            <button class="btn btn-outline-danger btn-sm" type="submit">Confirm termination</button>
                                        </form>
                                    </details>
                                @elseif(auth()->user()->role === \App\Models\User::ROLE_RENTER && $booking->status === 'completed' && $booking->reviews->isEmpty())
                                    <a class="btn btn-outline-dark btn-sm" href="{{ route('reviews.index') }}">Write a review</a>
                                @elseif($booking->status === 'confirmed')
                                    <span class="small text-muted-soft">Move-in {{ $booking->start_date->format('M j, Y') }}</span>
                                @elseif($booking->status === 'completed')
                                    <span class="small text-muted-soft">Rental completed</span>
                                @elseif($booking->status === 'terminated' && $booking->ended_early_at)
                                    <span class="small text-muted-soft">Ended {{ $booking->ended_early_at->format('M j, Y') }}</span>
                                @else
                                    <span class="text-muted-soft">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="record-empty"><x-empty-state title="No rental activity yet" message="Approved requests and confirmed bookings will appear here." :href="route('properties.index')" action="Browse rentals" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$bookings" /></div>
    </div>
@endsection
