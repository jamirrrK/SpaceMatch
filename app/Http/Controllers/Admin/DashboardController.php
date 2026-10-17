<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Space;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'total_users' => User::count(),
            'total_owners' => User::where('role', 'owner')->count(),
            'total_renters' => User::where('role', 'renter')->count(),
            'owners_waiting_verification' => User::where('role', 'owner')->whereNotNull('verification_required_at')->count(),
            'total_properties' => Property::count(),
            'pending_property_reviews' => Property::where('listing_status', 'draft')->count(),
            'approved_properties' => Property::where('listing_status', 'published')->count(),
            'total_spaces' => Space::count(),
            'available_spaces' => Space::where('availability_status', 'available')->count(),
            'pending_booking_requests' => Booking::where('status', 'pending')->count(),
            'confirmed_bookings' => Booking::where('status', 'confirmed')->count(),
            'active_rentals' => Booking::where('status', 'active')->count(),
            'total_payments' => Payment::count(),
            'pending_payments' => Payment::where('payment_status', 'pending')->count(),
            'unresolved_reports' => MaintenanceRequest::whereNotIn('status', ['resolved', 'closed'])->count(),
            'total_listings' => Space::where('listing_status', 'published')->count(),
            'active_properties' => Property::whereIn('status', ['active', 'available'])->count(),
            'inactive_properties' => Property::where('status', 'inactive')->count(),
            'active_bookings' => Booking::whereIn('status', ['pending', 'confirmed', 'active'])->count(),
            'pending_requests' => Booking::where('status', 'pending')->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentUsers' => User::latest()->take(5)->get(),
            'recentProperties' => Property::with('owner')->latest()->take(5)->get(),
            'recentBookings' => Booking::with(['renter', 'space.property'])->latest()->take(5)->get(),
            'recentPayments' => Payment::with(['booking.renter', 'booking.space.property'])->latest()->take(5)->get(),
            'recentAuditLogs' => AuditLog::with('user')->latest()->take(5)->get(),
            'recentMaintenanceRequests' => MaintenanceRequest::with(['booking.renter', 'booking.space.property'])->latest()->take(5)->get(),
        ]);
    }
}
