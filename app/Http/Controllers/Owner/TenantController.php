<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['confirmed', 'active'])],
        ]);

        $bookings = Booking::visibleTo($request->user())
            ->whereIn('status', ['confirmed', 'active'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('reference', 'like', '%'.$search.'%')
                        ->orWhereHas('renter', fn ($renter) => $renter->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('space', fn ($space) => $space->where('space_name', 'like', '%'.$search.'%'))
                        ->orWhereHas('space.property', fn ($property) => $property->where('property_name', 'like', '%'.$search.'%'));
                });
            })
            ->with(['renter:id,name', 'space.property', 'payments'])
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        $totalActive = Booking::visibleTo($request->user())->where('status', 'active')->count();
        $totalConfirmed = Booking::visibleTo($request->user())->where('status', 'confirmed')->count();

        return view('owner.tenants.index', compact('bookings', 'filters', 'totalActive', 'totalConfirmed'));
    }
}
