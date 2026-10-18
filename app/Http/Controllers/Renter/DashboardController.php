<?php

namespace App\Http\Controllers\Renter;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\Space;
use App\Models\ViewingAppointment;
use App\Services\StudentDiscovery;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $renter = $request->user();
        $renterId = $renter->id;

        $stats = [
            'active_bookings' => Booking::where('renter_id', $renterId)
                ->whereIn('status', ['confirmed', 'active'])
                ->count(),

            'pending_requests' => RentalRequest::where('renter_id', $renterId)
                ->whereIn('status', [
                    RentalRequest::STATUS_PENDING,
                    RentalRequest::STATUS_MORE_INFORMATION,
                ])
                ->count(),

            'completed_rentals' => Booking::where('renter_id', $renterId)
                ->where('status', 'completed')
                ->count(),

            'outstanding_balances' => Payment::whereHas(
                'booking',
                fn ($query) => $query->where('renter_id', $renterId)
            )
                ->whereIn('payment_status', ['pending', 'partially_paid'])
                ->selectRaw('COALESCE(SUM(CASE WHEN amount > paid_amount THEN amount - paid_amount ELSE 0 END), 0) AS balance')
                ->value('balance'),

            'saved_properties' => $renter->savedProperties()->count(),

            'saved_spaces' => $renter->savedSpaces()->count(),

            'open_inquiries' => Inquiry::where('renter_id', $renterId)
                ->where('status', 'open')
                ->count(),

            'unread_notifications' => $renter->unreadNotifications()->count(),
        ];

        $preferences = $renter->renterPreference;
        $recommendationFilters = StudentDiscovery::filters($preferences, $renter->is_student);
        $recommendations = Space::publiclyListed()->with(['property.images', 'images', 'amenities'])
            ->withExists(['savedByUsers as is_saved' => fn ($users) => $users->whereKey($renterId)]);
        StudentDiscovery::apply($recommendations, $recommendationFilters);
        if (isset($recommendationFilters['available_from'])) {
            StudentDiscovery::availableOn($recommendations, $recommendationFilters['available_from'], (int) ($preferences?->capacity ?? 1));
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if (isset($recommendationFilters[$key])) {
                $recommendations->where('price', $operator, $recommendationFilters[$key]);
            }
        }
        if ($preferences?->capacity) {
            $recommendations->where('capacity', '>=', $preferences->capacity);
        }
        if ($preferences?->preferred_location) {
            $recommendations->whereHas('property', fn ($property) => $property->where(function ($location) use ($preferences): void {
                foreach (['city', 'barangay', 'province', 'address'] as $column) {
                    $location->orWhere($column, 'like', '%'.$preferences->preferred_location.'%');
                }
            }));
        }
        foreach ($preferences?->preferred_amenity_ids ?? [] as $id) {
            $recommendations->whereHas('amenities', fn ($amenity) => $amenity->whereKey($id));
        }

        return view('renter.dashboard', [
            'preferences' => $preferences,
            'recommendationFilters' => $recommendationFilters,
            'availableSpaces' => $recommendations->latest()->limit(3)->get(),
            'recentInquiries' => Inquiry::where('renter_id', $renterId)->with('space.property')->latest()->limit(3)->get(),
            'upcomingViewings' => ViewingAppointment::where('renter_id', $renterId)
                ->whereIn('status', ['pending', 'scheduled', 'rescheduled'])
                ->where(fn ($query) => $query->where('scheduled_start_at', '>=', now())->orWhere('requested_start_at', '>=', now()))->with('space.property')->latest()->limit(3)->get(),
            'recentMaintenance' => MaintenanceRequest::whereHas('booking', fn ($booking) => $booking->where('renter_id', $renterId))
                ->with('booking.space.property')->latest()->limit(3)->get(),
            'upcomingBooking' => Booking::where('renter_id', $renterId)->whereIn('status', ['confirmed', 'active'])
                ->with('space.property')->orderBy('start_date')->first(),

            'stats' => $stats,

            'recentBookings' => Booking::where('renter_id', $renterId)
                ->with(['space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'recentRequests' => RentalRequest::where('renter_id', $renterId)
                ->with(['space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'recentPayments' => Payment::whereHas(
                'booking',
                fn ($query) => $query->where('renter_id', $renterId)
            )
                ->with(['booking.space.property'])
                ->latest()
                ->take(5)
                ->get(),

            'recentNotifications' => $renter->notifications()
                ->latest()
                ->take(5)
                ->get(),
        ]);
    }
}
