<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingInformationRequest extends Model
{
    protected $fillable = ['booking_id', 'owner_id', 'question', 'response', 'responded_at'];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
