@extends('layouts.app')

@section('title', 'Reviews | SpaceMatch')

@section('content')
    <div class="container py-5">
        <x-record-heading title="Customer reviews" description="Feedback connected to your rental activity." />
        @if($eligibleBookings->isNotEmpty())
            <section class="info-panel p-4 mb-4" aria-labelledby="write-review-title">
                <h2 class="panel-title mb-3" id="write-review-title">Share your experience</h2>
                <div class="row g-3">
                    @foreach($eligibleBookings as $booking)
                        <div class="col-lg-6">
                            <form class="border rounded-3 p-3 h-100" method="POST" action="{{ route('reviews.store', $booking) }}">
                                @csrf
                                <h3 class="h6">{{ $booking->space->property->property_name }} · {{ $booking->space->space_name }}</h3>
                                <label class="form-label" for="rating-{{ $booking->id }}">Rating</label>
                                <select class="form-select mb-2" id="rating-{{ $booking->id }}" name="rating" required>
                                    <option value="">Choose a rating</option>
                                    @foreach([5, 4, 3, 2, 1] as $rating)
                                        <option value="{{ $rating }}">{{ $rating }} / 5</option>
                                    @endforeach
                                </select>
                                <label class="form-label" for="comment-{{ $booking->id }}">Review (optional)</label>
                                <textarea class="form-control mb-3" id="comment-{{ $booking->id }}" name="comment" rows="3" maxlength="2000"></textarea>
                                <button class="btn btn-primary-custom btn-sm" type="submit">Submit review</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
        <div class="row g-4">
            @forelse($reviews as $review)
                <div class="col-lg-6">
                    <div class="info-panel p-4 h-100">
                        <div class="fw-bold">{{ $review->booking->renter->name }}</div>
                        <div class="small text-muted-soft">{{ $review->booking->space->property->property_name }} · {{ $review->booking->space->space_name }}</div>
                        <x-rating :average="$review->rating" :count="1" /><p class="small text-muted-soft">{{ $review->created_at->format('M j, Y') }}</p>@if(auth()->user()->isAdministrator())<p>{{ $review->reports_count }} review reports</p>@endif
                        @if($review->hidden_at)<p class="small text-danger">Hidden from public listings after moderation.</p>@endif
                        @if(auth()->user()->isAdministrator())
                        <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}" class="my-3">@csrf @method('PATCH')<input type="hidden" name="hidden" value="{{ $review->hidden_at ? 0 : 1 }}"><label class="form-label" for="reason-{{ $review->id }}">Moderation reason</label><input class="form-control mb-2" id="reason-{{ $review->id }}" name="reason" minlength="5" maxlength="500" required><button class="btn btn-outline-dark btn-sm">{{ $review->hidden_at ? 'Restore public review' : 'Hide public review' }}</button></form>
                        @endif
                        @if($review->comment)<p class="text-muted-soft mb-0">{{ $review->comment }}</p>@endif
                    </div>
                </div>
            @empty
                <div class="col-12"><x-empty-state title="No reviews yet" message="Reviews associated with your rental activity will appear here." /></div>
            @endforelse
        </div>
        <div class="mt-4"><x-pagination :paginator="$reviews" /></div>
    </div>
@endsection
