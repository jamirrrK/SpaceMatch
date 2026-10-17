<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function index(Request $request): View
    {
        $requests = MaintenanceRequest::with('booking.space.property')
            ->whereHas('booking', fn ($booking) => $booking->visibleTo($request->user()))
            ->latest()->paginate(15);

        return view('maintenance.index', compact('requests'));
    }
}
