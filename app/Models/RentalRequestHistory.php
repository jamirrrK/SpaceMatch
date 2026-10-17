<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalRequestHistory extends Model
{
    protected $fillable = [
        'rental_request_id',
        'actor_id',
        'actor_role',
        'event',
        'from_status',
        'to_status',
        'notes',
    ];

    public function rentalRequest()
    {
        return $this->belongsTo(RentalRequest::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
