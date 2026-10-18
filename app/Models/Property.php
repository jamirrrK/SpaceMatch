<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'property_type', 'barangay', 'postal_code', 'latitude', 'longitude', 'listing_status',
    ];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'moderated_at' => 'datetime'];
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderByDesc('is_primary')->orderBy('display_order')->orderBy('id');
    }

    public function scopePubliclyListed(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'available'])->whereNull('moderated_at')
            ->where('listing_status', 'published')->whereHas('spaces', fn ($spaces) => $spaces
            ->where('listing_status', 'published')->where('availability_status', 'available'));
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
