@extends('layouts.app')

@section('title', 'Maintenance Requests | SpaceMatch')

@section('content')
    <div class="container py-5">
        <h1 class="display-6 fw-bold mb-4">Maintenance requests</h1>
        <div class="table-responsive info-panel p-3">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Booking</th>
                        <th>Issue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $request)
                        <tr>
                            <td>{{ $request->booking->space->space_name }}</td>
                            <td>{{ $request->description }}</td>
                            <td>{{ ucfirst($request->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-5">No maintenance requests to show yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $requests->links() }}</div>
    </div>
@endsection
