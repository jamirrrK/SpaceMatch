@extends('layouts.app')

@section('title', 'Reviews | SpaceMatch')

@section('content')
    <div class="container py-5">
        <h1 class="display-6 fw-bold mb-4">Customer reviews</h1>
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
                <div class="col-12"><div class="info-panel p-4 text-muted-soft">No reviews to show yet.</div></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $reviews->links() }}</div>
    </div>
@endsection
