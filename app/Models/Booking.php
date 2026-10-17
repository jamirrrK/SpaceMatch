<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            User::ROLE_ADMIN => $query,
            User::ROLE_OWNER => $query->whereHas('space.property', fn (Builder $property) => $property
                ->where('owner_id', $user->id)),
            User::ROLE_RENTER => $query->where('renter_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    protected $fillable = [
        'renter_id',
        'space_id',
        'start_date',
        'end_date',
        'status',
    ];

    public function renter()
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
