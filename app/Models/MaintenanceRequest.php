<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    protected $table = 'maintenance_requests';

    protected $fillable = [
        'booking_id',
        'description',
        'status',
        'owner_response',
        'resolved_at',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }
}
