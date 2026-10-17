<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ViewingAppointment extends Model
{
    protected $fillable = [
        'space_id',
        'renter_id',
        'owner_id',
        'requested_start_at',
        'scheduled_start_at',
        'status',
        'response_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_start_at' => 'datetime',
            'scheduled_start_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(fn (Builder $participants) => $participants
            ->where('renter_id', $user->id)->orWhere('owner_id', $user->id));
    }

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function renter()
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activities()
    {
        return $this->hasMany(ViewingActivity::class)->orderBy('created_at')->orderBy('id');
    }
}
