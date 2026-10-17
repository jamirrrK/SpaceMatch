<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'floor_area' => 'decimal:2', 'capacity' => 'integer', 'floor_number' => 'integer'];
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
