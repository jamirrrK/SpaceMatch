<?php

namespace App\Services;

use App\Models\RenterPreference;
use Illuminate\Database\Eloquent\Builder;

class StudentDiscovery
{
    public static function filters(?RenterPreference $preferences, bool $student): array
    {
        return array_filter([
            'student_friendly' => $student ? 1 : null,
            'school' => $preferences?->school_or_campus,
            'campus' => $preferences?->campus,
            'available_from' => $preferences?->move_in_date && $preferences->move_in_date->gte(today()) ? $preferences->move_in_date->toDateString() : null,
            'location' => $preferences?->preferred_location,
            'min_price' => $preferences?->budget_min,
            'max_price' => $preferences?->budget_max,
            'space_types' => $preferences?->preferred_types ?: ($preferences?->space_type ? [$preferences->space_type] : null),
            'capacity' => $preferences?->capacity,
            'max_distance_km' => $preferences?->max_distance_km,
            'amenities' => $preferences?->preferred_amenity_ids,
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public static function availableOn(Builder $query, string $date, int $capacity = 1): Builder
    {
        return $query->where(fn (Builder $availability) => $availability->whereNull('available_from')
            ->orWhereDate('available_from', '<=', $date))
            ->whereRaw(
                'COALESCE((SELECT SUM(bookings.occupants) FROM bookings WHERE bookings.space_id = spaces.id AND bookings.status IN (?, ?) AND bookings.start_date <= ? AND bookings.end_date >= ?), 0) + ? <= spaces.capacity',
                ['confirmed', 'active', $date, $date, $capacity]
            );
    }

    public static function apply(Builder $query, array $filters): Builder
    {
        if (! empty($filters['student_friendly'])) {
            $query->where('student_friendly', true);
        }
        if (! empty($filters['school'])) {
            $query->where('nearby_school', 'like', '%'.$filters['school'].'%');
        }
        if (! empty($filters['campus'])) {
            $query->where('nearby_school', 'like', '%'.$filters['campus'].'%');
        }
        if (! empty($filters['transport'])) {
            $query->where('transport_notes', 'like', '%'.$filters['transport'].'%');
        }
        if (isset($filters['max_distance_km'])) {
            $query->whereNotNull('campus_distance_km')->where('campus_distance_km', '<=', $filters['max_distance_km']);
        }
        if (! empty($filters['space_types'])) {
            $query->whereIn('space_type', $filters['space_types']);
        }
        if (! empty($filters['furnishing'])) {
            $query->where('furnishing', $filters['furnishing']);
        }

        return $query;
    }
}
