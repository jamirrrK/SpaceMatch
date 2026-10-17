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
                        </tr>
                    @empty
                        <tr><td colspan="4" class="record-empty"><x-empty-state title="No rental activity yet" message="Start exploring available spaces across the Philippines." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4"><x-pagination :paginator="$bookings" /></div>
    </div>
@endsection
