@extends('layouts.app')

@section('title', 'Maintenance Requests | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Maintenance requests" description="Keep track of recorded issues and their current status." />
        <div class="table-responsive info-panel overflow-hidden" role="region" tabindex="0" aria-label="Maintenance requests table">
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
                            <td><x-status-badge :status="$request->status" /></td>
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
