<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'ended_early_at' => 'datetime',
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
        'reference',
        'occupants',
        'monthly_rent',
        'deposit_amount',
        'advance_months',
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

    public function rentalRequest()
    {
        return $this->hasOne(RentalRequest::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(BookingStatusHistory::class)->oldest();
    }

    public function terminator()
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
