@extends('layouts.app')
@section('title', 'Payment proof review | SpaceMatch')
@section('content')
<div class="pb-4">
    <div class="mb-4">
        <div class="eyebrow mb-2">{{ auth()->user()->hasRole('owner') ? 'Owner workspace' : 'Administration' }}</div>
        <h1 class="page-heading mb-1">Payment submissions</h1>
        <p class="small text-muted-soft mb-0">Review payment evidence before changing a payment balance or issuing a receipt.</p>
    </div>
    <form class="d-flex flex-wrap align-items-end gap-2 mb-4" method="GET">
        <label class="form-label mb-0" for="submission-status">Filter status</label>
        <select class="form-select w-auto" id="submission-status" name="status">
            <option value="">All submissions</option>
            @foreach(['pending', 'verified', 'rejected'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="btn btn-outline-dark" type="submit">Apply filter</button>
    </form>

    <div class="d-grid gap-3">
        @forelse($submissions as $submission)
            <article class="panel p-4">
                <div class="d-flex flex-wrap justify-content-between gap-3">
                    <div>
                        <div class="eyebrow">{{ $submission->payment->booking->space->property->property_name }} · {{ $submission->payment->booking->space->space_name }}</div>
                        <h2 class="panel-title mt-1 mb-1">{{ $submission->submitter->name }} submitted ₱{{ number_format((float) $submission->amount, 2) }}</h2>
                        <p class="small text-muted-soft mb-1">
                            Booking {{ $submission->payment->booking->reference }} ·
                            {{ ucfirst(str_replace('_', ' ', $submission->payment_method)) }} ·
                            Paid {{ $submission->payment_date->format('M j, Y') }}
                        </p>
                        @if($submission->reference)<p class="small text-muted-soft mb-2">Reference: {{ $submission->reference }}</p>@endif
                        <x-status-badge :status="$submission->status" />
                        <a class="btn btn-sm btn-outline-dark ms-2" href="{{ route('payments.submissions.evidence', $submission) }}">Open payment proof</a>
                        @if($submission->status === 'rejected')
                            <p class="small text-danger mt-2 mb-0">Reason: {{ $submission->rejection_reason }}</p>
                        @elseif($submission->reviewer)
                            <p class="small text-muted-soft mt-2 mb-0">Reviewed by {{ $submission->reviewer->name }} on {{ $submission->reviewed_at?->format('M j, Y') }}.</p>
                        @endif
                    </div>
                    @if($submission->status === 'pending')
                        <div class="d-flex flex-column gap-2">
                            <form method="POST" action="{{ route(auth()->user()->hasRole('owner') ? 'owner.payments.submissions.review' : 'admin.payments.submissions.review', $submission) }}" data-confirm="Verify this payment evidence and add the amount to the renter's ledger?">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="decision" value="verified">
                                <button class="btn btn-primary-custom" type="submit">Verify and record</button>
                            </form>
                            <form method="POST" action="{{ route(auth()->user()->hasRole('owner') ? 'owner.payments.submissions.review' : 'admin.payments.submissions.review', $submission) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="decision" value="rejected">
                                <label class="form-label small" for="rejection-reason-{{ $submission->id }}">Reason for rejection</label>
                                <textarea class="form-control form-control-sm mb-2" id="rejection-reason-{{ $submission->id }}" name="rejection_reason" rows="2" maxlength="1000" required></textarea>
                                <button class="btn btn-outline-danger btn-sm" type="submit">Reject submission</button>
                            </form>
                        </div>
                    @endif
                </div>
            </article>
        @empty
            <x-empty-state title="No payment submissions found" message="Submitted proof from renters will appear here for review." :href="route('payments.index')" action="View payment records" />
        @endforelse
    </div>
    <div class="mt-4"><x-pagination :paginator="$submissions" /></div>
</div>
@endsection
