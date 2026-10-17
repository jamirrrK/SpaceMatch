<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'completed'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $booking = DB::transaction(function () use ($request, $booking, $data): Booking {
            $ownedBooking = Booking::visibleTo($request->user())
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();
            $allowedNext = match ($ownedBooking->status) {
                'confirmed' => 'active',
                'active' => 'completed',
                default => null,
            };
            if ($allowedNext !== $data['status']) {
                throw ValidationException::withMessages(['status' => 'This booking cannot make that status transition.']);
            }
            if ($data['status'] === 'active' && $ownedBooking->start_date->isFuture()) {
                throw ValidationException::withMessages(['status' => 'A booking can only become active on or after its move-in date.']);
            }
            if ($data['status'] === 'completed' && $ownedBooking->end_date->isFuture()) {
                throw ValidationException::withMessages(['status' => 'A booking can only be completed on or after its end date.']);
            }

            $previous = $ownedBooking->status;
            $ownedBooking->update(['status' => $data['status']]);
            BookingStatusHistory::query()->create([
                'booking_id' => $ownedBooking->id,
                'actor_id' => $request->user()->id,
                'from_status' => $previous,
                'to_status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $ownedBooking->load('renter');
        });

        $booking->renter->notify(new RentalWorkflowNotification(
            'Rental status updated',
            'Your booking '.$booking->reference.' is now '.$booking->status.'.',
            route('bookings.index')
        ));

        return back()->with('status', 'Booking status updated.');
    }
}
