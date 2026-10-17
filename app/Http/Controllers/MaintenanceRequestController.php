<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = MaintenanceRequest::with('booking.space.property')
            ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->latest()->paginate(15);

        $rentals = $request->user()->hasRole('renter')
            ? Booking::where('renter_id', $request->user()->id)->whereIn('status', ['confirmed', 'active'])->with('space.property')->get()
            : collect();

        return view('maintenance.index', compact('requests', 'rentals'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('renter'), 403);
        $data = $request->validate(['booking_id' => ['required', 'integer', 'exists:bookings,id'], 'description' => ['required', 'string', 'min:5', 'max:5000']]);
        $booking = Booking::whereKey($data['booking_id'])->where('renter_id', $request->user()->id)->whereIn('status', ['confirmed', 'active'])->with('space.property.owner')->firstOrFail();
        $maintenance = MaintenanceRequest::create(['booking_id' => $booking->id, 'description' => $data['description'], 'status' => 'open']);
        $booking->space->property->owner->notify(new RentalWorkflowNotification('A renter submitted a maintenance request.', route('maintenance.index')));

        return back()->with('status', 'Maintenance request sent to the property owner.');
    }
}
