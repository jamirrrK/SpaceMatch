<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingLifecycle
{
    public function cancelConfirmed(Booking $booking, User $actor, string $reason): Booking
    {
        $booking = DB::transaction(function () use ($booking, $actor, $reason): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertActorCanManage($locked, $actor);

            if ($locked->status !== 'confirmed' || ! $locked->start_date->isFuture()) {
                throw ValidationException::withMessages(['booking' => 'A confirmed booking can only be cancelled before its move-in date.']);
            }

            $this->transition($locked, $actor, 'cancelled', $reason);
            $this->cancelFutureObligations($locked, today());

            return $locked->load(['renter', 'space.property.owner']);
        });

        $this->notifyOtherParty($booking, $actor, 'Booking cancelled', 'A booking was cancelled: '.$reason);

        return $booking;
    }

    public function terminateActive(Booking $booking, User $actor, string $reason): Booking
    {
        $booking = DB::transaction(function () use ($booking, $actor, $reason): Booking {
            $locked = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertActorCanManage($locked, $actor);

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['booking' => 'Only an active rental can be terminated.']);
            }

            $locked->forceFill([
                'ended_early_at' => now(),
                'terminated_by' => $actor->id,
                'termination_reason' => $reason,
            ])->save();
            $this->transition($locked, $actor, 'terminated', $reason);
            $this->cancelFutureObligations($locked, today());

            return $locked->load(['renter', 'space.property.owner']);
        });

        $this->notifyOtherParty($booking, $actor, 'Rental terminated', 'An active rental was terminated: '.$reason);

        return $booking;
    }

    private function assertActorCanManage(Booking $booking, User $actor): void
    {
        if (! in_array($actor->role, [User::ROLE_RENTER, User::ROLE_OWNER], true)
            || ! Booking::visibleTo($actor)->whereKey($booking->id)->exists()) {
            abort(404);
        }
    }

    private function transition(Booking $booking, User $actor, string $status, string $notes): void
    {
        $previous = $booking->status;
        $booking->update(['status' => $status]);
        BookingStatusHistory::query()->create([
            'booking_id' => $booking->id,
            'actor_id' => $actor->id,
            'from_status' => $previous,
            'to_status' => $status,
            'notes' => $notes,
        ]);
    }

    private function cancelFutureObligations(Booking $booking, $effectiveDate): void
    {
        Payment::query()
            ->where('booking_id', $booking->id)
            ->whereIn('payment_status', ['pending', 'partially_paid'])
            ->whereDate('due_date', '>', $effectiveDate)
            ->update(['payment_status' => 'cancelled', 'updated_at' => now()]);
    }

    private function notifyOtherParty(Booking $booking, User $actor, string $title, string $message): void
    {
        $booking->loadMissing(['renter', 'space.property.owner']);
        $recipient = $actor->id === $booking->renter_id
            ? $booking->space->property->owner
            : $booking->renter;

        $recipient->notify(new RentalWorkflowNotification($title, $message, route('bookings.index')));
    }
}
