<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingInformationRequest;
use App\Models\Space;
use App\Notifications\RentalWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = Booking::visibleTo($request->user())
            ->with(['space.property', 'renter', 'informationRequests'])
            ->latest()->paginate(15);

        return view('bookings.index', compact('bookings'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasRole('renter'), 403);
        $data = $request->validate([
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after:start_date', 'before_or_equal:'.now()->addYears(10)->toDateString()],
            'occupants' => ['required', 'integer', 'min:1', 'max:10000'],
            'renter_message' => ['nullable', 'string', 'max:3000'],
        ]);

        $space = Space::publiclyListed()->with('property.owner')->findOrFail($data['space_id']);
        if ((int) $data['occupants'] > $space->capacity) {
            throw ValidationException::withMessages(['occupants' => 'The number of occupants exceeds this space’s capacity.']);
        }
        $days = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date']));
        $months = max(1, (int) ceil($days / 30));
        [$whole, $fraction] = array_pad(explode('.', (string) $space->price, 2), 2, '0');
        $unitCents = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        $booking = DB::transaction(function () use ($request, $data, $space, $months, $unitCents) {
            $locked = Space::whereKey($space->id)->lockForUpdate()->firstOrFail();
            if ($locked->listing_status !== 'published' || $locked->availability_status !== 'available') {
                throw ValidationException::withMessages(['space_id' => 'This rental space is no longer available.']);
            }
            $overlap = Booking::where('space_id', $locked->id)->whereIn('status', ['confirmed', 'active'])
                ->where('start_date', '<', $data['end_date'])->where('end_date', '>', $data['start_date'])
                ->lockForUpdate()->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['start_date' => 'The requested dates overlap an existing confirmed rental.']);
            }

            return Booking::create([
                'renter_id' => $request->user()->id,
                'space_id' => $locked->id,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'occupants' => $data['occupants'],
                'renter_message' => $data['renter_message'] ?? null,
                'agreed_amount' => intdiv($unitCents * $months, 100).'.'.str_pad((string) (($unitCents * $months) % 100), 2, '0', STR_PAD_LEFT),
                'status' => 'pending',
            ]);
        });

        $space->property->owner->notify(new RentalWorkflowNotification('A renter requested '.$space->space_name.'.', route('bookings.index')));
        $request->user()->notify(new RentalWorkflowNotification('Your rental request was sent to '.$space->property->property_name.'.', route('bookings.index')));

        return redirect()->route('bookings.index')->with('status', 'Your rental request has been sent.');
    }

    public function respond(Request $request, BookingInformationRequest $informationRequest)
    {
        abort_unless($request->user()->hasRole('renter'), 403);
        abort_unless((int) $informationRequest->booking->renter_id === (int) $request->user()->id, 404);
        $data = $request->validate(['response' => ['required', 'string', 'min:2', 'max:3000']]);
        abort_if($informationRequest->responded_at, 409);
        $informationRequest->update($data + ['responded_at' => now()]);
        $informationRequest->booking->update(['status' => 'pending']);
        $informationRequest->owner->notify(new RentalWorkflowNotification('A renter responded to your information request.', route('bookings.index')));

        return back()->with('status', 'Your response was sent to the property owner.');
    }
}
