<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $fillable = [
        'space_id',
        'renter_id',
        'owner_id',
        'status',
    ];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $participants) use ($user): void {
            $participants->where('renter_id', $user->id)
                ->orWhere('owner_id', $user->id);
        });
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
        return $this->hasMany(InquiryActivity::class)->orderBy('created_at')->orderBy('id');
    }
}
