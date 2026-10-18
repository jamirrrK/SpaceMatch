<?php

namespace App\Services;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ListingSearch
{
    public static function query(array $filters, ?User $user = null): Builder
    {
        $query = Space::publiclyListed()->with([
            'property.images' => fn ($images) => $images->where(fn (Builder $visible) => $visible
                ->whereNull('space_id')->orWhereHas('space', fn (Builder $space) => $space->where('listing_status', 'published'))),
            'images', 'amenities', 'property' => fn ($property) => $property->withReviewSummary(),
        ]);
        if ($user?->hasRole('renter')) {
            $query->withExists(['savedByUsers as is_saved' => fn (Builder $users) => $users->whereKey($user->id)]);
        }

        StudentDiscovery::apply($query, $filters);
        $searchTerms = array_values(array_filter([
            ($filters['q'] ?? null),

        ], fn ($term) => is_string($term) && trim($term) !== ''));

        $searchTerms = array_map(fn ($term) => trim($term), $searchTerms);

        if (! empty($searchTerms)) {
            $canSearchExactAddress = Schema::hasColumn('properties', 'location_visibility');
            $query->where(function (Builder $match) use ($searchTerms, $canSearchExactAddress): void {
                foreach ($searchTerms as $term) {
                    $like = '%'.$term.'%';
                    $match->orWhere(function (Builder $locationMatch) use ($like, $canSearchExactAddress): void {
                        $locationMatch->whereHas('property', fn (Builder $property) => $property->where(function (Builder $propertyMatch) use ($like, $canSearchExactAddress): void {
                            $propertyMatch->where('property_name', 'like', $like)
                                ->orWhere('description', 'like', $like)
                                ->orWhere('city', 'like', $like)
                                ->orWhere('barangay', 'like', $like);
                            if ($canSearchExactAddress) {
                                $propertyMatch->orWhere(fn ($address) => $address->where('location_visibility', 'exact')->where('address', 'like', $like));
                            }
                            $propertyMatch->orWhere('province', 'like', $like);
                        }));
                    });
                    $match->orWhere('space_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('amenities', fn (Builder $amenity) => $amenity->where('name', 'like', $like));
                }
            });
        }

        if (! empty($filters['location'])) {
            $query->whereHas('property', fn (Builder $property) => $property->where(function (Builder $location) use ($filters): void {
                foreach (['city', 'barangay', 'province'] as $column) {
                    $location->orWhere($column, 'like', '%'.$filters['location'].'%');
                }
            }));
        }

        if (! empty($filters['property_type'])) {
            $query->whereHas('property', fn (Builder $property) => $property
                ->where('property_type', $filters['property_type']));
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (isset($filters['capacity'])) {
            $query->where('capacity', '>=', $filters['capacity']);
        }

        foreach (['bedrooms', 'bathrooms'] as $countFilter) {
            if (isset($filters[$countFilter])) {
                $query->where($countFilter, '>=', $filters[$countFilter]);
            }
        }

        if (isset($filters['available_from'])) {
            StudentDiscovery::availableOn($query, $filters['available_from'], (int) ($filters['capacity'] ?? 1));
        }

        // One existence constraint per amenity requires every selected amenity.
        foreach ($filters['amenities'] ?? [] as $amenityId) {
            $query->whereHas('amenities', fn (Builder $amenity) => $amenity->whereKey($amenityId));
        }

        foreach (['region_code', 'province_code', 'city_code', 'barangay_code'] as $code) {
            if (!empty($filters[$code])) {
                if (Schema::hasColumn('properties', $code)) {
                    $query->whereHas('property', fn ($property) => $property->where($code, $filters[$code]));
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }
        if (isset($filters['near_lat'], $filters['near_lng'])) {
            $locationColumns = ['latitude', 'longitude', 'location_visibility', 'location_confirmed_at'];
            $hasPublicLocationSchema = collect($locationColumns)->every(
                fn (string $column) => Schema::hasColumn('properties', $column)
            );
            if ($hasPublicLocationSchema) {
                $lat = (float) $filters['near_lat']; $lng = (float) $filters['near_lng'];
                $radius = (float) ($filters['radius_km'] ?? 10);
                $query->whereHas('property', function ($property) use ($lat, $lng, $radius) {
                    // Use only the public approximation for private pins, never their exact location.
                    $latSql = "CASE WHEN location_visibility = 'exact' THEN latitude ELSE ROUND(latitude, 2) END";
                    $lngSql = "CASE WHEN location_visibility = 'exact' THEN longitude ELSE ROUND(longitude, 2) END";
                    $property->whereNotNull('location_confirmed_at')->where('location_visibility', '!=', 'hidden')
                        ->whereRaw("$latSql BETWEEN ? AND ?", [$lat - $radius/111, $lat + $radius/111])
                        ->whereRaw("$lngSql BETWEEN ? AND ?", [$lng - $radius/(111*cos(deg2rad($lat))), $lng + $radius/(111*cos(deg2rad($lat)))])
                        ->whereRaw("(6371 * 2 * ASIN(SQRT(POWER(SIN(RADIANS($latSql - ?)/2),2) + COS(RADIANS(?))*COS(RADIANS($latSql))*POWER(SIN(RADIANS($lngSql - ?)/2),2)))) <= ?", [$lat, $lat, $lng, $radius]);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price')->orderByDesc('spaces.id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('spaces.id'),
            'updated' => $query->orderByDesc('spaces.updated_at')->orderByDesc('spaces.id'),
            'relevant' => $query->orderByRaw(
                'CASE WHEN spaces.space_name = ? THEN 0 WHEN spaces.space_name LIKE ? THEN 1 ELSE 2 END',
                [($filters['q'] ?? ''), ($filters['q'] ?? '').'%']
            )->latest('spaces.created_at')->orderByDesc('spaces.id'),
            default => $query->latest('spaces.created_at')->orderByDesc('spaces.id'),
        };

        return $query;
    }
}
