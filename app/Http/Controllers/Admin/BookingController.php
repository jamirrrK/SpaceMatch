<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $query = Booking::query()->with(['renter', 'space.property']);
        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status'),
        ];

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';
            $query->where(function ($builder) use ($term) {
                $builder->whereHas('renter', fn ($renter) => $renter->where('name', 'like', $term)->orWhere('email', 'like', $term))
                    ->orWhereHas('space', fn ($space) => $space->where('space_name', 'like', $term))
                    ->orWhereHas('space.property', fn ($property) => $property->where('property_name', 'like', $term));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $bookings = $query->latest()->paginate(15)->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'filters'));
    }
}
