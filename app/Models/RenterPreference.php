<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenterPreference extends Model
{
    protected $fillable = [
        'campus', 'max_distance_km', 'preferred_types', 'preferred_amenity_ids',
        'school_or_campus',
        'move_in_date',
        'budget_min',
        'budget_max',
        'preferred_location',
        'space_type',
        'capacity',
        'amenities_notes',
    ];

    protected function casts(): array
    {
        return [
            'preferred_types' => 'array', 'preferred_amenity_ids' => 'array',
            'max_distance_km' => 'decimal:2',
            'move_in_date' => 'date',
            'budget_min' => 'decimal:2',
            'budget_max' => 'decimal:2',
            'capacity' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
