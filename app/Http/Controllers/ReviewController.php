<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function index(Request $request): View
    {
        $reviews = Review::with(['booking.renter', 'booking.space.property'])
            ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->latest()->paginate(15);

        $eligibleBookings = $request->user()->role === User::ROLE_RENTER
            ? Booking::query()->where('renter_id', $request->user()->id)
                ->where('status', 'completed')->whereDoesntHave('reviews')
                ->with(['space.property'])->latest()->get()
            : collect();

        return view('reviews.index', compact('reviews', 'eligibleBookings'));
    }

    public function moderate(Request $request, Review $review)
    {
        $data = $request->validate(['hidden' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        DB::transaction(function () use ($request, $review, $data): void {
            $review->forceFill(['hidden_at' => $data['hidden'] ? now() : null, 'moderated_by' => $request->user()->id, 'moderation_reason' => $data['reason']])->save();
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'review_moderated', 'description' => json_encode(['review_id' => $review->id, 'hidden' => $data['hidden'], 'reason' => $data['reason']], JSON_THROW_ON_ERROR)]);
        });

        return back()->with('status', 'Review moderation recorded.');
    }

    public function store(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $booking, $data): void {
            $eligibleBooking = Booking::query()
                ->whereKey($booking->id)
                ->where('renter_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($eligibleBooking->status !== 'completed') {
                throw ValidationException::withMessages(['booking' => 'A review can only be added after a rental is completed.']);
            }
            if ($eligibleBooking->reviews()->exists()) {
                throw ValidationException::withMessages(['booking' => 'You have already reviewed this completed rental.']);
            }

            $eligibleBooking->reviews()->create($data);
        });

        return redirect()->route('reviews.index')->with('status', 'Thank you. Your review has been recorded.');
    }
}
