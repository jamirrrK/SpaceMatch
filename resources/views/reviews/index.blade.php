@extends('layouts.app')

@section('title', 'Reviews | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Customer reviews" description="Feedback connected to your rental activity." />
        <div class="row g-4">
            @forelse($reviews as $review)
                <div class="col-lg-6">
                    <div class="info-panel p-4 h-100">
                        <div class="fw-bold">{{ $review->booking->renter->name }}</div>
                        <div class="text-warning fw-semibold my-2">{{ $review->rating }}/5</div>
                        <p class="text-muted-soft mb-0">{{ $review->comment }}</p>
                    </div>
                </div>
            @empty
                <div class="col-12"><x-empty-state title="No reviews yet" message="Reviews associated with your rental activity will appear here." /></div>
            @endforelse
        </div>
        <div class="mt-4"><x-pagination :paginator="$reviews" /></div>
    </div>
@endsection
