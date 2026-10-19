<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'property_name',
        'description',
        'address',
        'city',
        'province',
        'status',
        'region_code', 'province_code', 'city_code', 'barangay_code', 'location_visibility', 'location_confirmed_at',
        'property_type', 'barangay', 'postal_code', 'latitude', 'longitude', 'listing_status',
    ];

    protected function casts(): array
    {
        return ['is_synthetic' => 'boolean', 'location_confirmed_at' => 'datetime', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'moderated_at' => 'datetime'];
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderByDesc('is_primary')->orderBy('display_order')->orderBy('id');
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->when(!config('marketplace.public_listings_enabled'), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(
                !(app()->environment('testing') && config('marketplace.allow_qa_listings'))
                    && Schema::hasColumn('properties', 'is_synthetic'),
                fn ($q) => $q->where('is_synthetic', false)
            )
            ->whereIn('status', ['active', 'available'])->whereNull('moderated_at')
            ->where('listing_status', 'published')->whereHas('spaces', fn ($spaces) => $spaces
            ->where('listing_status', 'published')->where('availability_status', 'available'));
    }

    public function scopeWithReviewSummary(Builder $query): Builder
    {
        $base = \Illuminate\Support\Facades\DB::table('reviews')->join('bookings', 'bookings.id', '=', 'reviews.booking_id')
            ->join('spaces', 'spaces.id', '=', 'bookings.space_id')->whereColumn('spaces.property_id', 'properties.id')
            ->when(Schema::hasColumn('reviews', 'hidden_at'), fn ($reviews) => $reviews->whereNull('reviews.hidden_at'))
            ->where('bookings.status', 'completed');
        return $query->addSelect(['rating_average' => (clone $base)->selectRaw('AVG(reviews.rating)'),
            'rating_count' => (clone $base)->selectRaw('COUNT(*)')]);
    }

    public function publicLocation(): ?array
    {
        if ($this->location_visibility === 'hidden' || !$this->location_confirmed_at || $this->latitude === null || $this->longitude === null) return null;
        $exact = $this->location_visibility === 'exact';
        return ['lat' => $exact ? (float) $this->latitude : round((float) $this->latitude, 2),
            'lng' => $exact ? (float) $this->longitude : round((float) $this->longitude, 2),
            'precision' => $exact ? 'Owner-confirmed exact pin (not independently verified)' : 'Approximate area (about 1 km grid)'];
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function spaces()
    {
        return $this->hasMany(Space::class);
    }

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_properties')->withTimestamps();
    }
}
