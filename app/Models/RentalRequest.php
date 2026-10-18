<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RentalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_MORE_INFORMATION = 'more_information_requested';

    protected $fillable = [
        'terms_snapshot', 'terms_accepted_at',
        'renter_id',
        'space_id',
        'move_in_date',
        'occupants',
        'duration_months',
        'contact_phone',
        'message',
        'renter_response',
        'status',
        'response_message',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'terms_snapshot' => 'array', 'terms_accepted_at' => 'datetime',
            'move_in_date' => 'date',
            'responded_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            User::ROLE_ADMIN => $query,
            User::ROLE_OWNER => $query->whereHas('space.property', fn (Builder $property) => $property->where('owner_id', $user->id)),
            User::ROLE_RENTER => $query->where('renter_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function renter()
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(RentalRequestHistory::class)->oldest();
    }
}
