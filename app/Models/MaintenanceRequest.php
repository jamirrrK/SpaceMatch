<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceRequest extends Model
{
    protected $table = 'maintenance_requests';

    protected $fillable = [
        'evidence_path',
        'booking_id',
        'description',
        'status',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function statusHistory()
    {
        return $this->hasMany(MaintenanceRequestStatusHistory::class)->oldest();
    }
}
