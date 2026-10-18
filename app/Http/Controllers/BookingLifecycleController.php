<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Services\BookingLifecycle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BookingLifecycleController extends Controller
{
    public function cancel(Request $request, Booking $booking, BookingLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->assertRoleCanAct($request->user(), $booking);
        $lifecycle->cancelConfirmed($booking, $request->user(), $data['reason']);

        return back()->with('status', 'Booking cancelled. Existing payment and booking history has been retained.');
    }

    public function terminate(Request $request, Booking $booking, BookingLifecycle $lifecycle): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $this->assertRoleCanAct($request->user(), $booking);
        $lifecycle->terminateActive($booking, $request->user(), $data['reason']);

        return back()->with('status', 'Rental terminated. Confirmed payments and history have been retained.');
    }

    private function assertRoleCanAct(User $user, Booking $booking): void
    {
        if (! in_array($user->role, [User::ROLE_RENTER, User::ROLE_OWNER], true)
            || ! Booking::visibleTo($user)->whereKey($booking->id)->exists()) {
            abort(404);
        }
    }
}
