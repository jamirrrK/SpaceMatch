@extends('layouts.app')

@section('title', 'Bookings | SpaceMatch')

@section('content')
    <div class="container py-5">
        <h1 class="display-6 fw-bold mb-4">Bookings</h1>
        <div class="table-responsive info-panel p-3">
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
                        <tr><td colspan="4" class="text-center text-muted py-5">No bookings to show yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $bookings->links() }}</div>
    </div>
@endsection
