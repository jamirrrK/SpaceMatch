<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RenterPreference extends Model
{
    protected $fillable = [
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
