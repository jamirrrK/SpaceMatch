<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Space extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'space_name',
        'space_type',
        'description',
        'price',
        'capacity',
        'availability_status',
        'floor_area', 'floor_number', 'listing_status',
        'bedrooms', 'bathrooms', 'available_from',
        'deposit_amount', 'advance_months', 'house_rules', 'move_in_requirements', 'cancellation_policy',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'floor_area' => 'decimal:2',
            'capacity' => 'integer',
            'floor_number' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'available_from' => 'date',
            'deposit_amount' => 'decimal:2',
            'advance_months' => 'integer',
        ];
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderByDesc('is_primary')->orderBy('display_order')->orderBy('id');
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->where('listing_status', 'published')->where('availability_status', 'available')
            ->whereHas('property', fn ($property) => $property->publiclyListed());
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_spaces')->withTimestamps();
    }
}
